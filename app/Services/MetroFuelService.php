<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MetroFuelService
{
    private const BROWSER_UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

    /**
     * @return array<string, mixed>|null
     */
    public function getDashboardData(): ?array
    {
        $ttl = (int) config('services.metrofuel.cache_ttl', 1800);
        $key = 'metrofuel_' . hash('sha256', implode('|', [
            (string) config('services.metrofuel.base_url'),
            (string) config('services.metrofuel.north'),
            (string) config('services.metrofuel.south'),
            (string) config('services.metrofuel.east'),
            (string) config('services.metrofuel.west'),
        ]));

        return Cache::remember($key, max(60, $ttl), function () {
            return $this->fetchStations();
        });
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchStations(): ?array
    {
        $base = rtrim((string) config('services.metrofuel.base_url', 'https://metrofueltracker.com'), '/');
        if ($base === '') {
            return null;
        }

        $north = config('services.metrofuel.north');
        $south = config('services.metrofuel.south');
        $east = config('services.metrofuel.east');
        $west = config('services.metrofuel.west');

        if (! is_numeric($north) || ! is_numeric($south) || ! is_numeric($east) || ! is_numeric($west)) {
            return null;
        }

        try {
            $response = $this->httpClient()->get($base.'/api/stations', [
                'north' => (float) $north,
                'south' => (float) $south,
                'east' => (float) $east,
                'west' => (float) $west,
            ]);

            if (! $response->successful()) {
                Log::warning('MetroFuel stations request failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            }

            $json = $response->json();
            if (! is_array($json) || ! isset($json['stations']) || ! is_array($json['stations'])) {
                return null;
            }

            return $this->normalizePayload($json['stations'], $base);
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    /**
     * @param  array<int, mixed>  $stations
     * @return array<string, mixed>
     */
    private function normalizePayload(array $stations, string $baseUrl): array
    {
        $regionLabel = (string) config('services.metrofuel.region_label', 'MetroFuel');
        $disclaimer = (string) config('services.metrofuel.disclaimer', '');
        $maxRows = max(3, min(12, (int) config('services.metrofuel.table_rows', 6)));

        $dieselRows = [];
        $gasRows = [];
        $latestTs = null;

        foreach ($stations as $s) {
            if (! is_array($s)) {
                continue;
            }
            $name = (string) ($s['name'] ?? '');
            $brand = (string) ($s['brand'] ?? '');
            $prices = $s['prices'] ?? null;
            if (! is_array($prices)) {
                continue;
            }

            foreach ($prices as $fuelKey => $p) {
                if (! is_array($p) || ! isset($p['price'])) {
                    continue;
                }
                $isOos = (bool) ($p['isOos'] ?? false);
                if ($isOos) {
                    continue;
                }
                $price = (float) $p['price'];
                $fuelKeyStr = (string) $fuelKey;
                $reportedAt = $this->parseReportedAt($p['reportedAt'] ?? null);
                if ($reportedAt !== null) {
                    $latestTs = $latestTs === null || $reportedAt->gt($latestTs) ? $reportedAt : $latestTs;
                }

                $row = [
                    'station' => $name,
                    'brand' => $brand,
                    'fuel_label' => $fuelKeyStr,
                    'price' => $price,
                    'reported_at' => $reportedAt,
                ];

                if ($this->isDieselKey($fuelKeyStr)) {
                    $dieselRows[] = $row;
                } elseif ($this->isGasolineKey($fuelKeyStr)) {
                    $gasRows[] = $row;
                }
            }
        }

        usort($dieselRows, fn ($a, $b) => $a['price'] <=> $b['price']);
        usort($gasRows, fn ($a, $b) => $a['price'] <=> $b['price']);

        $dieselRows = array_slice($dieselRows, 0, $maxRows);
        $gasRows = array_slice($gasRows, 0, $maxRows);

        $cheapestDiesel = $dieselRows[0] ?? null;
        $cheapestGas = $gasRows[0] ?? null;

        return [
            'region_label' => $regionLabel,
            'disclaimer' => $disclaimer,
            'source_url' => $baseUrl.'/',
            'station_count' => count($stations),
            'updated_at' => $latestTs ?? now(),
            'cheapest_diesel' => $cheapestDiesel,
            'cheapest_gasoline' => $cheapestGas,
            'diesel_rows' => $dieselRows,
            'gasoline_rows' => $gasRows,
        ];
    }

    private function parseReportedAt(mixed $v): ?Carbon
    {
        if (! is_string($v) || $v === '') {
            return null;
        }
        try {
            return Carbon::parse($v);
        } catch (\Throwable) {
            return null;
        }
    }

    private function isDieselKey(string $k): bool
    {
        return str_contains(strtoupper($k), 'DIESEL');
    }

    private function isGasolineKey(string $k): bool
    {
        $u = strtoupper($k);

        return str_contains($u, 'GAS')
            || str_contains($u, 'GASOLINE')
            || str_contains($u, 'UNLEADED')
            || preg_match('/\b(91|95|97|RON)\b/', $u) === 1;
    }

    private function httpClient(): \Illuminate\Http\Client\PendingRequest
    {
        $options = [];
        if (! config('services.metrofuel.verify_ssl', true)) {
            $options['verify'] = false;
        }

        return Http::timeout(30)
            ->withOptions($options)
            ->withHeaders([
                'User-Agent' => self::BROWSER_UA,
                'Accept' => 'application/json',
                'Accept-Language' => 'en-US,en;q=0.9',
            ]);
    }
}
