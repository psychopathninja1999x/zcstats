<?php

namespace App\Services;

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
            $weatherItem = $w['weather'][0] ?? [];

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
            ];
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    private function httpClient(): \Illuminate\Http\Client\PendingRequest
    {
        $options = [];
        if (! config('services.openweather.verify_ssl', true)) {
            $options['verify'] = false;
        }

        return Http::timeout(12)->withOptions($options);
    }
}
