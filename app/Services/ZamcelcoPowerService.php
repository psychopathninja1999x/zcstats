<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ZamcelcoPowerService
{
    private const BROWSER_UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

    /**
     * @return array<string, mixed>|null
     */
    public function getDashboardData(): ?array
    {
        $ttl = (int) config('services.zamcelco.cache_ttl', 3600);

        return Cache::remember('zamcelco_power_rates_api', max(60, $ttl), function () {
            return $this->fetchPowerRates();
        });
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchPowerRates(): ?array
    {
        $url = config('services.zamcelco.power_rates_api_url');
        if (! is_string($url) || $url === '') {
            return null;
        }

        try {
            $response = $this->httpClient()->get($url);
            if (! $response->successful()) {
                Log::warning('ZAMCELCO power-rates API request failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            }

            $json = $response->json();
            if (! is_array($json) || (int) ($json['code'] ?? 0) !== 200) {
                Log::warning('ZAMCELCO power-rates API unexpected payload', ['payload' => $json]);

                return null;
            }

            $data = $json['data'] ?? null;
            if (! is_array($data)) {
                return null;
            }

            $rates = $data['rates'] ?? [];
            if (! is_array($rates)) {
                $rates = [];
            }

            $normalized = [];
            foreach ($rates as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $normalized[] = [
                    'code' => (string) ($row['code'] ?? ''),
                    'description' => (string) ($row['description'] ?? ''),
                    'current_total' => isset($row['current_total']) ? (float) $row['current_total'] : null,
                    'previous_total' => isset($row['previous_total']) ? (float) $row['previous_total'] : null,
                    'change' => isset($row['change']) ? (float) $row['change'] : null,
                ];
            }

            $residential = null;
            foreach ($normalized as $row) {
                if ($row['code'] === 'R') {
                    $residential = $row;
                    break;
                }
            }

            $visibleRates = array_values(array_filter(
                $normalized,
                fn (array $row): bool => ! $this->isHighVoltageRateHidden($row)
            ));

            return [
                'current_month' => isset($data['current_month']) ? (string) $data['current_month'] : null,
                'previous_month' => isset($data['previous_month']) ? (string) $data['previous_month'] : null,
                'next_month' => isset($data['next_month']) ? (string) $data['next_month'] : null,
                'rates' => $visibleRates,
                'residential' => $residential,
                'api_source' => $url,
            ];
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    /**
     * High Voltage row is hidden from the dashboard until we surface it again.
     *
     * @param  array{code: string, description: string, ...}  $row
     */
    private function isHighVoltageRateHidden(array $row): bool
    {
        $code = strtoupper((string) ($row['code'] ?? ''));
        if (in_array($code, ['HV', 'HVT', 'HVOLT'], true)) {
            return true;
        }

        return str_contains(strtolower((string) ($row['description'] ?? '')), 'high voltage');
    }

    private function httpClient(): PendingRequest
    {
        $options = [];
        if (! config('services.zamcelco.verify_ssl', true)) {
            $options['verify'] = false;
        }

        return Http::timeout(25)
            ->withOptions($options)
            ->withHeaders([
                'User-Agent' => self::BROWSER_UA,
                'Accept' => 'application/json',
                'Accept-Language' => 'en-US,en;q=0.9',
            ]);
    }
}
