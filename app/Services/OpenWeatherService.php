<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenWeatherService
{
    /**
     * @return array<string, mixed>|null
     */
    public function getDashboardData(): ?array
    {
        $key = config('services.openweather.key');
        if (! is_string($key) || $key === '') {
            return null;
        }

        $lat = (float) config('services.openweather.lat');
        $lon = (float) config('services.openweather.lon');

        try {
            $weather = $this->httpClient()
                ->get('https://api.openweathermap.org/data/2.5/weather', [
                    'lat' => $lat,
                    'lon' => $lon,
                    'appid' => $key,
                    'units' => 'metric',
                ]);

            if (! $weather->successful()) {
                Log::warning('OpenWeather current weather request failed', [
                    'status' => $weather->status(),
                    'body' => $weather->body(),
                ]);

                return null;
            }

            $w = $weather->json();
            $main = $w['main'] ?? [];
            $weatherItem = is_array($w['weather'][0] ?? null) ? $w['weather'][0] : [];
            $icon = (string) ($weatherItem['icon'] ?? '');

            $air = $this->httpClient()
                ->get('https://api.openweathermap.org/data/2.5/air_pollution', [
                    'lat' => $lat,
                    'lon' => $lon,
                    'appid' => $key,
                ]);

            $aqi = null;
            $aqiLabel = null;
            if ($air->successful()) {
                $list = $air->json('list');
                if (is_array($list) && isset($list[0]['main']['aqi'])) {
                    $aqi = (int) $list[0]['main']['aqi'];
                    $aqiLabel = match ($aqi) {
                        1 => 'Good',
                        2 => 'Fair',
                        3 => 'Moderate',
                        4 => 'Poor',
                        5 => 'Very poor',
                        default => null,
                    };
                }
            }

            $updatedAt = isset($w['dt']) && is_numeric($w['dt'])
                ? Carbon::createFromTimestamp((int) $w['dt'])
                : now();

            return [
                'location' => $w['name'] ?? 'Zamboanga City',
                'country' => $w['sys']['country'] ?? '',
                'temp' => isset($main['temp']) ? round((float) $main['temp'], 1) : null,
                'feels_like' => isset($main['feels_like']) ? round((float) $main['feels_like'], 1) : null,
                'description' => isset($weatherItem['description'])
                    ? ucfirst((string) $weatherItem['description'])
                    : '',
                'humidity' => isset($main['humidity']) ? (int) $main['humidity'] : null,
                'updated_at' => $updatedAt,
                'aqi' => $aqi,
                'aqi_label' => $aqiLabel,
                'weather_icon' => $icon,
                'is_night' => $icon !== '' && str_ends_with($icon, 'n'),
                'weather_effect' => $this->normalizeWeatherEffect($weatherItem),
            ];
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    /**
     * Map OpenWeather condition codes to dashboard animation presets.
     *
     * @param  array<string, mixed>  $weatherItem
     */
    private function normalizeWeatherEffect(array $weatherItem): string
    {
        $id = (int) ($weatherItem['id'] ?? 0);
        $main = strtoupper((string) ($weatherItem['main'] ?? ''));

        if ($id >= 200 && $id < 300) {
            return 'thunderstorm';
        }
        if ($id >= 300 && $id < 400) {
            return 'drizzle';
        }
        if ($id >= 500 && $id < 600) {
            return 'rain';
        }
        if ($id >= 600 && $id < 700) {
            return 'snow';
        }
        if ($id >= 701 && $id < 800) {
            return 'fog';
        }
        if ($id === 800) {
            return 'clear';
        }
        if ($id >= 801 && $id <= 804) {
            return 'clouds';
        }

        return match ($main) {
            'THUNDERSTORM' => 'thunderstorm',
            'DRIZZLE' => 'drizzle',
            'RAIN' => 'rain',
            'SNOW' => 'snow',
            'MIST', 'SMOKE', 'HAZE', 'DUST', 'FOG', 'SAND', 'ASH', 'SQUALL', 'TORNADO' => 'fog',
            'CLEAR' => 'clear',
            'CLOUDS' => 'clouds',
            default => 'clouds',
        };
    }

    private function httpClient(): PendingRequest
    {
        $options = [];
        if (! config('services.openweather.verify_ssl', true)) {
            $options['verify'] = false;
        }

        return Http::timeout(12)->withOptions($options);
    }
}
