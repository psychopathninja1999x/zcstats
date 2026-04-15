<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * City-level fuel prices from Gasmoto (https://gasmoto.app/map) via their public Supabase REST API.
 * The web app ships the anon key; we use the same read-only access as the map.
 */
class GasmotoFuelService
{
    private const BROWSER_UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

    /**
     * @return array<string, mixed>|null
     */
    public function getDashboardData(): ?array
    {
        $ttl = (int) config('services.gasmoto.cache_ttl', 1800);
        $key = 'gasmoto_fuel_v3_' . hash('sha256', implode('|', [
            (string) config('services.gasmoto.supabase_url'),
            (string) config('services.gasmoto.city_id'),
            (string) json_encode(config('services.gasmoto.doe_columns', [])),
        ]));

        return Cache::remember($key, max(60, $ttl), function () {
            return $this->fetchAndNormalize();
        });
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchAndNormalize(): ?array
    {
        $base = rtrim((string) config('services.gasmoto.supabase_url', ''), '/');
        $anon = (string) config('services.gasmoto.supabase_anon_key', '');
        $cityId = (int) config('services.gasmoto.city_id', 0);

        if ($base === '' || $anon === '' || $cityId < 1) {
            return null;
        }

        try {
            $prices = $this->fetchFuelPrices($base, $anon, $cityId);
            if ($prices === null) {
                return null;
            }

            $stationCount = $this->fetchStationCount($base, $anon);

            return $this->normalizePayload($prices, $stationCount, $base);
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    /**
     * @return array<int, mixed>|null
     */
    private function fetchFuelPrices(string $base, string $anon, int $cityId): ?array
    {
        $url = $base.'/rest/v1/fuel_prices';
        $response = $this->httpClient()->withHeaders($this->supabaseHeaders($anon))->get($url, [
            'city_id' => 'eq.'.$cityId,
            'select' => 'id,brand,price,updated_at,products(name)',
            'order' => 'price.asc',
        ]);

        if (! $response->successful()) {
            Log::warning('Gasmoto fuel_prices request failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        }

        $json = $response->json();
        if (! is_array($json)) {
            return null;
        }

        return $json;
    }

    private function fetchStationCount(string $base, string $anon): ?int
    {
        $area = (string) config('services.gasmoto.stations_area', 'Zamboanga City');
        if ($area === '') {
            return null;
        }

        $url = $base.'/rest/v1/stations';
        $response = $this->httpClient()
            ->withHeaders(array_merge($this->supabaseHeaders($anon), [
                'Prefer' => 'count=exact',
                'Range' => '0-0',
            ]))
            ->get($url, [
                'area' => 'eq.'.$area,
                'is_active' => 'eq.true',
                'select' => 'id',
            ]);

        if (! $response->successful()) {
            return null;
        }

        $range = $response->header('Content-Range');
        if (! is_string($range) || ! preg_match('#/(\d+|\*)$#', $range, $m)) {
            return null;
        }

        return $m[1] === '*' ? null : (int) $m[1];
    }

    /**
     * @param  array<int, mixed>  $rows
     * @return array<string, mixed>
     */
    private function normalizePayload(array $rows, ?int $stationCount, string $supabaseBase): array
    {
        $regionLabel = (string) config('services.gasmoto.region_label', 'Gasmoto · Zamboanga City');
        $disclaimer = (string) config('services.gasmoto.disclaimer', '');
        $cityLabel = (string) config('services.gasmoto.city_label', 'Zamboanga City');
        $sourceMap = (string) config('services.gasmoto.map_url', 'https://gasmoto.app/map');

        /** @var array<string, string> $doeLabels */
        $doeLabels = config('services.gasmoto.doe_columns', []);
        if ($doeLabels === []) {
            $doeLabels = [
                'unleaded_doe' => 'UnleadedDOE',
                'premium_95_doe' => 'Premium95DOE',
                'premium_98' => 'Premium98',
                'diesel_doe' => 'DieselDOE',
                'premium_diesel_doe' => 'Premium DieselDOE',
                'kerosene' => 'Kerosene',
            ];
        }
        /** @var array<string, array<string, array{price: float, product: string, reported_at: ?Carbon}>> $byBrand */
        $byBrand = [];
        $latestTs = null;

        foreach ($rows as $r) {
            if (! is_array($r)) {
                continue;
            }
            $brand = (string) ($r['brand'] ?? '');
            if ($brand === '') {
                continue;
            }
            $price = isset($r['price']) ? (float) $r['price'] : null;
            if ($price === null) {
                continue;
            }
            $fuelLabel = '';
            $products = $r['products'] ?? null;
            if (is_array($products) && isset($products['name'])) {
                $fuelLabel = (string) $products['name'];
            }
            if ($fuelLabel === '') {
                continue;
            }

            $doeKey = $this->mapGasmotoProductToDoeKey($fuelLabel);
            if ($doeKey === null || ! isset($doeLabels[$doeKey])) {
                continue;
            }

            $reportedAt = $this->parseReportedAt($r['updated_at'] ?? null);
            if ($reportedAt !== null) {
                $latestTs = $latestTs === null || $reportedAt->gt($latestTs) ? $reportedAt : $latestTs;
            }

            $cell = ['price' => $price, 'product' => $fuelLabel, 'reported_at' => $reportedAt];
            if (! isset($byBrand[$brand][$doeKey]) || $price < $byBrand[$brand][$doeKey]['price']) {
                $byBrand[$brand][$doeKey] = $cell;
            }
        }

        ksort($byBrand, SORT_STRING);

        $doeRows = [];
        foreach ($byBrand as $brand => $cells) {
            $doeRows[] = [
                'brand' => $brand,
                'cells' => $cells,
            ];
        }

        $cheapestDiesel = $this->pickCheapestAcrossBrands($byBrand, ['diesel_doe']);
        $cheapestGas = $this->pickCheapestAcrossBrands($byBrand, ['unleaded_doe', 'premium_95_doe', 'premium_98']);

        return [
            'region_label' => $regionLabel,
            'disclaimer' => $disclaimer,
            'source_url' => $sourceMap,
            'station_count' => $stationCount ?? count($rows),
            'updated_at' => $latestTs ?? now(),
            'doe_columns' => array_map(
                fn (string $k, string $label) => ['key' => $k, 'label' => $label],
                array_keys($doeLabels),
                array_values($doeLabels)
            ),
            'doe_rows' => $doeRows,
            'cheapest_diesel' => $this->cheapestToLegacyRow($cheapestDiesel, $cityLabel),
            'cheapest_gasoline' => $this->cheapestGasToLegacyRow($cheapestGas, $cityLabel),
            'diesel_rows' => [],
            'gasoline_rows' => [],
        ];
    }

    /**
     * Map Gasmoto `products.name` to DOE column keys (see config `services.gasmoto.doe_columns`).
     */
    private function mapGasmotoProductToDoeKey(string $name): ?string
    {
        $u = strtoupper(trim($name));

        if (str_contains($u, 'KEROSENE')) {
            return 'kerosene';
        }

        if (str_contains($u, 'DIESEL') && (str_contains($u, 'PLUS') || str_contains($u, 'PREMIUM'))) {
            return 'premium_diesel_doe';
        }

        if (str_contains($u, 'DIESEL')) {
            return 'diesel_doe';
        }

        if (preg_match('/RON\s*97|RON\s*98|RON\s*100/', $u) === 1) {
            return 'premium_98';
        }

        if (preg_match('/RON\s*95/', $u) === 1) {
            return 'premium_95_doe';
        }

        if (preg_match('/RON\s*91/', $u) === 1) {
            return 'unleaded_doe';
        }

        return null;
    }

    /**
     * @param  array<string, array<string, array{price: float, product: string, reported_at: ?Carbon}>>  $byBrand
     * @param  array<int, string>  $doeKeys
     * @return array{brand: string, doe_key: string, price: float, product: string, reported_at: ?Carbon}|null
     */
    private function pickCheapestAcrossBrands(array $byBrand, array $doeKeys): ?array
    {
        $best = null;
        foreach ($byBrand as $brand => $cells) {
            foreach ($doeKeys as $dk) {
                if (! isset($cells[$dk])) {
                    continue;
                }
                $p = $cells[$dk]['price'];
                if ($best === null || $p < $best['price']) {
                    $best = [
                        'brand' => $brand,
                        'doe_key' => $dk,
                        'price' => $p,
                        'product' => $cells[$dk]['product'],
                        'reported_at' => $cells[$dk]['reported_at'],
                    ];
                }
            }
        }

        return $best;
    }

    /**
     * @param  array{brand: string, doe_key: string, price: float, product: string, reported_at: ?Carbon}|null  $c
     * @return array<string, mixed>|null
     */
    private function cheapestToLegacyRow(?array $c, string $cityLabel): ?array
    {
        if ($c === null) {
            return null;
        }

        return [
            'station' => $c['brand'].' · '.$cityLabel,
            'brand' => $c['brand'],
            'fuel_label' => $c['product'],
            'price' => $c['price'],
            'reported_at' => $c['reported_at'],
        ];
    }

    /**
     * @param  array{brand: string, doe_key: string, price: float, product: string, reported_at: ?Carbon}|null  $c
     * @return array<string, mixed>|null
     */
    private function cheapestGasToLegacyRow(?array $c, string $cityLabel): ?array
    {
        if ($c === null) {
            return null;
        }

        return [
            'station' => $c['brand'].' · '.$cityLabel,
            'brand' => $c['brand'],
            'fuel_label' => $c['product'],
            'price' => $c['price'],
            'reported_at' => $c['reported_at'],
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

    /**
     * @return array<string, string>
     */
    private function supabaseHeaders(string $anon): array
    {
        return [
            'apikey' => $anon,
            'Authorization' => 'Bearer '.$anon,
            'Accept' => 'application/json',
        ];
    }

    private function httpClient(): \Illuminate\Http\Client\PendingRequest
    {
        $options = [];
        if (! config('services.gasmoto.verify_ssl', true)) {
            $options['verify'] = false;
        }

        return Http::timeout(25)
            ->withOptions($options)
            ->withHeaders([
                'User-Agent' => self::BROWSER_UA,
            ]);
    }
}
