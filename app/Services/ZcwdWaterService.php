<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ZcwdWaterService
{
    private const BROWSER_UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

    /**
     * @return array<string, mixed>|null
     */
    public function getReservoirData(): ?array
    {
        $ttl = (int) config('services.zcwd.cache_ttl', 900);
        $url = config('services.zcwd.url');

        if (! is_string($url) || $url === '') {
            return null;
        }

        return Cache::remember('zcwd_reservoir_data', max(60, $ttl), function () use ($url) {
            return $this->fetchAndParse($url);
        });
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchAndParse(string $url): ?array
    {
        try {
            $options = [];
            if (! config('services.zcwd.verify_ssl', true)) {
                $options['verify'] = false;
            }

            $response = Http::timeout(20)
                ->withOptions($options)
                ->withHeaders([
                    'User-Agent' => self::BROWSER_UA,
                    'Accept' => 'text/html,application/xhtml+xml',
                    'Accept-Language' => 'en-US,en;q=0.9',
                ])
                ->get($url);

            if (! $response->successful()) {
                Log::warning('ZCWD water page request failed', [
                    'status' => $response->status(),
                ]);

                return null;
            }

            $html = $response->body();

            return $this->parseHtml($html);
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function parseHtml(string $html): ?array
    {
        $normalM = null;
        if (preg_match('/Normal\s+Level\s*=\s*([\d.]+)\s*m/i', $html, $m)) {
            $normalM = (float) $m[1];
        }

        $previousM = null;
        $previousWhen = null;
        if (preg_match('/Previous:\s*<strong>([\d.]+)m<\/strong>\s+on\s+([^<]+)</is', $html, $m)) {
            $previousM = (float) $m[1];
            $previousWhen = trim(html_entity_decode($m[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }

        $currentM = null;
        if (preg_match('/([\d.]+)m\s*<\/h1>/i', $html, $m)) {
            $currentM = (float) $m[1];
        }

        $asOf = null;
        if (preg_match('/id\s*=\s*"data"\s*>([^<]+)</i', $html, $m)) {
            $asOf = trim(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }

        $turbidityLine = null;
        $turbidityNtu = null;
        if (preg_match('/id\s*=\s*"turbidity"\s*>(.*?)<\/span>/is', $html, $m)) {
            $turbidityLine = trim(preg_replace('/\s+/', ' ', strip_tags(str_replace('<br>', ' ', $m[1]))));
            if (preg_match_all('/([\d.]+)\s*NTU/i', $turbidityLine, $ntuMatches) && $ntuMatches[1] !== []) {
                $turbidityNtu = (float) end($ntuMatches[1]);
            }
        }

        if ($currentM === null) {
            return null;
        }

        $capacityPercent = null;
        if ($normalM !== null && $normalM > 0) {
            $capacityPercent = round(($currentM / $normalM) * 100, 1);
        }

        $status = '—';
        if ($normalM !== null) {
            $delta = $currentM - $normalM;
            if ($delta < -0.05) {
                $status = 'Below normal';
            } elseif ($delta > 0.05) {
                $status = 'Above normal';
            } else {
                $status = 'Normal';
            }
        }

        return [
            'normal_m' => $normalM,
            'previous_m' => $previousM,
            'previous_when' => $previousWhen,
            'current_m' => $currentM,
            'as_of' => $asOf,
            'turbidity_line' => $turbidityLine,
            'turbidity_ntu' => $turbidityNtu,
            'capacity_percent' => $capacityPercent,
            'status' => $status,
        ];
    }
}
