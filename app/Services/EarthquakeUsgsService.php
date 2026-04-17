<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EarthquakeUsgsService
{
    /**
     * Recent earthquakes near the configured point (USGS FDSN feed; no API key).
     *
     * @return array<string, mixed>|null
     */
    public function getDashboardData(): ?array
    {
        if (! config('services.earthquake.enabled', true)) {
            return null;
        }

        $lat = (float) config('services.earthquake.lat');
        $lon = (float) config('services.earthquake.lon');
        $radiusKm = (float) config('services.earthquake.radius_km');
        $minMag = (float) config('services.earthquake.min_magnitude');
        $days = max(1, (int) config('services.earthquake.lookback_days'));
        $limit = max(1, min(200, (int) config('services.earthquake.limit')));
        $ttl = max(60, (int) config('services.earthquake.cache_ttl'));

        $cacheKey = 'zcstats.earthquake.v1.'.md5(implode('|', [
            (string) $lat,
            (string) $lon,
            (string) $radiusKm,
            (string) $minMag,
            (string) $days,
            (string) $limit,
        ]));

        $hit = Cache::get($cacheKey);
        if ($hit !== null) {
            return is_array($hit) ? $hit : null;
        }

        $data = $this->fetchFromUsgs($lat, $lon, $radiusKm, $minMag, $days, $limit);
        if (is_array($data)) {
            Cache::put($cacheKey, $data, $ttl);
        }

        return $data;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchFromUsgs(float $lat, float $lon, float $radiusKm, float $minMag, int $days, int $limit): ?array
    {
        $start = now()->timezone(config('app.timezone'))->subDays($days)->startOfDay();

        $url = rtrim((string) config('services.earthquake.usgs_query_url'), '/');
        if ($url === '') {
            return null;
        }

        try {
            $response = $this->httpClient()->get($url, [
                'format' => 'geojson',
                'latitude' => $lat,
                'longitude' => $lon,
                'maxradiuskm' => $radiusKm,
                'minmagnitude' => $minMag,
                'starttime' => $start->format('Y-m-d'),
                'orderby' => 'time',
                'limit' => $limit,
            ]);

            if (! $response->successful()) {
                Log::warning('USGS earthquake query failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            }

            $geo = $response->json();
            $features = is_array($geo['features'] ?? null) ? $geo['features'] : [];
            $tz = (string) config('app.timezone');
            $label = (string) config('services.earthquake.city_label', 'Zamboanga City');

            $events = [];
            foreach ($features as $feature) {
                if (! is_array($feature)) {
                    continue;
                }
                $props = is_array($feature['properties'] ?? null) ? $feature['properties'] : [];
                if (($props['type'] ?? 'earthquake') !== 'earthquake') {
                    continue;
                }
                $coords = $feature['geometry']['coordinates'] ?? null;
                if (! is_array($coords) || count($coords) < 2) {
                    continue;
                }
                $evLon = (float) $coords[0];
                $evLat = (float) $coords[1];
                $depthKm = isset($coords[2]) && is_numeric($coords[2]) ? round((float) $coords[2], 1) : null;
                $mag = isset($props['mag']) && is_numeric($props['mag']) ? round((float) $props['mag'], 1) : null;
                if ($mag === null) {
                    continue;
                }
                $timeMs = isset($props['time']) && is_numeric($props['time']) ? (int) $props['time'] : null;
                $at = $timeMs !== null
                    ? Carbon::createFromTimestampMs($timeMs)->timezone($tz)
                    : null;
                $place = isset($props['place']) ? (string) $props['place'] : '';
                $urlEvent = isset($props['url']) ? (string) $props['url'] : '';

                $events[] = [
                    'lat' => $evLat,
                    'lon' => $evLon,
                    'mag' => $mag,
                    'depth_km' => $depthKm,
                    'place' => $place,
                    'at' => $at,
                    'url' => $urlEvent,
                    'distance_km' => round($this->haversineKm($lat, $lon, $evLat, $evLon), 1),
                ];
            }

            $mapEvents = [];
            foreach ($events as $e) {
                $at = $e['at'];
                $mapEvents[] = [
                    'lat' => $e['lat'],
                    'lon' => $e['lon'],
                    'mag' => $e['mag'],
                    'place' => $e['place'],
                    'depth_km' => $e['depth_km'],
                    'distance_km' => $e['distance_km'],
                    'time_label' => $at instanceof Carbon ? $at->isoFormat('MMM D, YYYY · h:mm A') : '',
                    'url' => $e['url'],
                ];
            }

            return [
                'ref_lat' => $lat,
                'ref_lon' => $lon,
                'ref_label' => $label,
                'radius_km' => $radiusKm,
                'min_magnitude' => $minMag,
                'lookback_days' => $days,
                'fetched_at' => now()->timezone($tz),
                'usgs_url' => (string) config('services.earthquake.usgs_home_url'),
                'phivolcs_url' => (string) config('services.earthquake.phivolcs_url'),
                'events' => $events,
                'map' => [
                    'ref' => ['lat' => $lat, 'lon' => $lon, 'label' => $label],
                    'radius_km' => $radiusKm,
                    'events' => $mapEvents,
                ],
            ];
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
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
        $verify = filter_var(config('services.earthquake.verify_ssl', true), FILTER_VALIDATE_BOOLEAN);

        return Http::timeout(20)
            ->connectTimeout(10)
            ->when(! $verify, fn (PendingRequest $r) => $r->withoutVerifying())
            ->withHeaders(['Accept' => 'application/json, application/geo+json']);
    }
}
