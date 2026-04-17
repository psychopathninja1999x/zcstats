<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TyphoonGdacsService
{
    /**
     * Active tropical cyclones near the reference point (GDACS API; JTWC among sources).
     *
     * @return array<string, mixed>|null
     */
    public function getDashboardData(): ?array
    {
        if (! config('services.typhoon.enabled', true)) {
            return null;
        }

        $refLat = (float) config('services.typhoon.lat');
        $refLon = (float) config('services.typhoon.lon');
        $radiusKm = (float) config('services.typhoon.radius_km');
        $maxStorms = max(1, min(8, (int) config('services.typhoon.max_storms')));
        $listTtl = max(120, (int) config('services.typhoon.list_cache_ttl'));
        $geomTtl = max(300, (int) config('services.typhoon.geometry_cache_ttl'));
        $lookbackDays = max(30, (int) config('services.typhoon.list_lookback_days'));

        $listKey = 'zcstats.typhoon.list.v1.'.md5(implode('|', [
            (string) $refLat,
            (string) $refLon,
            (string) $radiusKm,
            (string) $maxStorms,
            (string) $lookbackDays,
        ]));

        $listHit = Cache::get($listKey);
        if (! is_array($listHit)) {
            $listHit = $this->fetchEventList($lookbackDays);
            if ($listHit === null) {
                return null;
            }
            Cache::put($listKey, $listHit, $listTtl);
        }

        $candidates = $this->filterStormsNear($listHit, $refLat, $refLon, $radiusKm, $maxStorms);
        $tz = (string) config('app.timezone');
        $label = (string) config('services.typhoon.city_label', 'Zamboanga City');

        $storms = [];
        $mapStorms = [];

        foreach ($candidates as $row) {
            $geomKey = 'zcstats.typhoon.geom.v1.'.md5(implode('|', [
                (string) $row['eventtype'],
                (string) $row['eventid'],
                (string) $row['episodeid'],
            ]));

            $geom = Cache::get($geomKey);
            if (! is_array($geom)) {
                $fetched = $this->fetchGeometry(
                    (string) $row['eventtype'],
                    (int) $row['eventid'],
                    (int) $row['episodeid']
                );
                if (is_array($fetched)) {
                    $geom = $fetched;
                    Cache::put($geomKey, $geom, $geomTtl);
                } else {
                    $geom = ['past' => [], 'forecast' => []];
                }
            }

            $windKmh = null;
            $windText = null;
            if (isset($row['severitydata']) && is_array($row['severitydata'])) {
                $sd = $row['severitydata'];
                if (isset($sd['severity']) && is_numeric($sd['severity'])) {
                    $windKmh = round((float) $sd['severity'], 0);
                }
                if (isset($sd['severitytext']) && is_string($sd['severitytext'])) {
                    $windText = $sd['severitytext'];
                }
            }

            $modified = null;
            if (isset($row['datemodified']) && is_string($row['datemodified'])) {
                try {
                    $modified = Carbon::parse($row['datemodified'])->timezone($tz);
                } catch (\Throwable) {
                    $modified = null;
                }
            }

            $storms[] = [
                'name' => (string) ($row['name'] ?? $row['description'] ?? 'Tropical cyclone'),
                'eventname' => (string) ($row['eventname'] ?? ''),
                'lat' => $row['lat'],
                'lon' => $row['lon'],
                'distance_km' => $row['distance_km'],
                'alertlevel' => (string) ($row['alertlevel'] ?? 'Green'),
                'country' => (string) ($row['country'] ?? ''),
                'source' => (string) ($row['source'] ?? ''),
                'wind_kmh' => $windKmh,
                'wind_text' => $windText,
                'report_url' => (string) ($row['report_url'] ?? ''),
                'datemodified' => $modified,
                'track_past' => $geom['past'],
                'track_forecast' => $geom['forecast'],
            ];

            $mapStorms[] = [
                'name' => (string) (($row['name'] ?? '') !== '' ? $row['name'] : (($row['eventname'] ?? '') !== '' ? $row['eventname'] : ($row['description'] ?? ''))),
                'alert' => (string) ($row['alertlevel'] ?? 'Green'),
                'lat' => $row['lat'],
                'lon' => $row['lon'],
                'distance_km' => $row['distance_km'],
                'url' => (string) ($row['report_url'] ?? ''),
                'past' => $geom['past'],
                'forecast' => $geom['forecast'],
            ];
        }

        return [
            'ref_lat' => $refLat,
            'ref_lon' => $refLon,
            'ref_label' => $label,
            'radius_km' => $radiusKm,
            'fetched_at' => now()->timezone($tz),
            'gdacs_url' => (string) config('services.typhoon.gdacs_url'),
            'pagasa_url' => (string) config('services.typhoon.pagasa_url'),
            'storms' => $storms,
            'map' => [
                'ref' => ['lat' => $refLat, 'lon' => $refLon, 'label' => $label],
                'radius_km' => $radiusKm,
                'storms' => $mapStorms,
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchEventList(int $lookbackDays): ?array
    {
        $base = rtrim((string) config('services.typhoon.gdacs_list_url'), '/');
        if ($base === '') {
            return null;
        }

        $from = now()->timezone(config('app.timezone'))->subDays($lookbackDays)->format('Y-m-d');
        $to = now()->timezone(config('app.timezone'))->addDays(45)->format('Y-m-d');

        try {
            $response = $this->httpClient()
                ->get($base, [
                    'eventtype' => 'TC',
                    'fromdate' => $from,
                    'todate' => $to,
                ]);

            if (! $response->successful()) {
                Log::warning('GDACS typhoon list failed', [
                    'status' => $response->status(),
                ]);

                return null;
            }

            $json = $response->json();

            return is_array($json) ? $json : null;
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $geojson
     * @return array{past: list<list<array{0: float, 1: float}>>, forecast: list<list<array{0: float, 1: float}>>}
     */
    private function parseGeometryGeoJson(array $geojson): array
    {
        $past = [];
        $forecast = [];
        $features = $geojson['features'] ?? null;
        if (! is_array($features)) {
            return ['past' => [], 'forecast' => []];
        }

        foreach ($features as $feature) {
            if (! is_array($feature)) {
                continue;
            }
            $g = $feature['geometry'] ?? null;
            if (! is_array($g)) {
                continue;
            }
            $type = (string) ($g['type'] ?? '');
            $props = is_array($feature['properties'] ?? null) ? $feature['properties'] : [];
            $isForecast = filter_var($props['forecast'] ?? false, FILTER_VALIDATE_BOOLEAN);

            if ($type === 'LineString') {
                $path = $this->lineStringToLatLngs($g['coordinates'] ?? []);
                if ($path !== []) {
                    if ($isForecast) {
                        $forecast[] = $path;
                    } else {
                        $past[] = $path;
                    }
                }
            } elseif ($type === 'MultiLineString') {
                foreach ($g['coordinates'] ?? [] as $line) {
                    if (! is_array($line)) {
                        continue;
                    }
                    $path = $this->lineStringToLatLngs($line);
                    if ($path !== []) {
                        if ($isForecast) {
                            $forecast[] = $path;
                        } else {
                            $past[] = $path;
                        }
                    }
                }
            }
        }

        return ['past' => $past, 'forecast' => $forecast];
    }

    /**
     * @param  list<array<int, float>>  $coords
     * @return list<array{0: float, 1: float}>
     */
    private function lineStringToLatLngs(array $coords): array
    {
        $out = [];
        foreach ($coords as $c) {
            if (! is_array($c) || count($c) < 2) {
                continue;
            }
            $out[] = [(float) $c[1], (float) $c[0]];
        }

        return $out;
    }

    /**
     * @return array{past: list<list<array{0: float, 1: float}>>, forecast: list<list<array{0: float, 1: float}>>}|null
     */
    private function fetchGeometry(string $eventType, int $eventId, int $episodeId): ?array
    {
        $tpl = (string) config('services.typhoon.gdacs_geometry_url_template');
        if ($tpl === '' || ! str_contains($tpl, '{eventtype}')) {
            return null;
        }

        $url = str_replace(
            ['{eventtype}', '{eventid}', '{episodeid}'],
            [rawurlencode($eventType), (string) $eventId, (string) $episodeId],
            $tpl
        );

        try {
            $response = $this->httpClient()->timeout(45)->get($url);
            if (! $response->successful()) {
                Log::warning('GDACS typhoon geometry failed', [
                    'status' => $response->status(),
                    'eventid' => $eventId,
                ]);

                return null;
            }
            $json = $response->json();

            return is_array($json) ? $this->parseGeometryGeoJson($json) : null;
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $featureCollection
     * @return list<array<string, mixed>>
     */
    private function filterStormsNear(
        array $featureCollection,
        float $refLat,
        float $refLon,
        float $radiusKm,
        int $maxStorms
    ): array {
        $features = $featureCollection['features'] ?? null;
        if (! is_array($features)) {
            return [];
        }

        $rows = [];
        foreach ($features as $feature) {
            if (! is_array($feature)) {
                continue;
            }
            $props = $feature['properties'] ?? null;
            $geom = $feature['geometry'] ?? null;
            if (! is_array($props) || ! is_array($geom)) {
                continue;
            }
            if (($props['eventtype'] ?? '') !== 'TC') {
                continue;
            }
            $iCur = $props['iscurrent'] ?? '';
            if ($iCur !== 'true' && $iCur !== true && $iCur !== 1 && $iCur !== '1') {
                continue;
            }
            if (($geom['type'] ?? '') !== 'Point') {
                continue;
            }
            $coords = $geom['coordinates'] ?? null;
            if (! is_array($coords) || count($coords) < 2) {
                continue;
            }
            $lon = (float) $coords[0];
            $lat = (float) $coords[1];
            $dist = $this->haversineKm($refLat, $refLon, $lat, $lon);
            if ($dist > $radiusKm) {
                continue;
            }

            $urls = $props['url'] ?? [];
            $reportUrl = '';
            if (is_array($urls) && isset($urls['report']) && is_string($urls['report'])) {
                $reportUrl = $urls['report'];
            }

            $episodeId = isset($props['episodeid']) && is_numeric($props['episodeid'])
                ? (int) $props['episodeid']
                : 0;
            $eventId = isset($props['eventid']) && is_numeric($props['eventid'])
                ? (int) $props['eventid']
                : 0;

            if ($eventId === 0 || $episodeId === 0) {
                continue;
            }

            $rows[] = [
                'name' => (string) ($props['name'] ?? ''),
                'eventname' => (string) ($props['eventname'] ?? ''),
                'description' => (string) ($props['description'] ?? ''),
                'alertlevel' => (string) ($props['alertlevel'] ?? 'Green'),
                'country' => (string) ($props['country'] ?? ''),
                'source' => (string) ($props['source'] ?? ''),
                'severitydata' => is_array($props['severitydata'] ?? null) ? $props['severitydata'] : null,
                'datemodified' => $props['datemodified'] ?? null,
                'lat' => $lat,
                'lon' => $lon,
                'distance_km' => round($dist, 0),
                'report_url' => $reportUrl,
                'eventid' => $eventId,
                'episodeid' => $episodeId,
                'eventtype' => 'TC',
            ];
        }

        usort($rows, fn (array $a, array $b): int => ($a['distance_km'] <=> $b['distance_km']));

        return array_slice($rows, 0, $maxStorms);
    }

    private function haversineKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $r = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return $r * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    private function httpClient(): PendingRequest
    {
        $verify = filter_var(config('services.typhoon.verify_ssl', true), FILTER_VALIDATE_BOOLEAN);

        return Http::timeout(25)
            ->connectTimeout(12)
            ->when(! $verify, fn (PendingRequest $r) => $r->withoutVerifying())
            ->withHeaders([
                'Accept' => 'application/json, application/geo+json',
                'User-Agent' => 'ZCStats/1.0 (civic dashboard; +https://github.com/)',
            ]);
    }
}
