<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PrayerTimesService
{
    private const MUSLIMPRO_REFERENCE_URL = 'https://app.muslimpro.com/prayer-times/philippines/prayer-times-zamboanga/1679432';

    /**
     * @return array<string, mixed>|null
     */
    public function getDashboardData(): ?array
    {
        if (! config('services.prayer_times.enabled', true)) {
            return null;
        }

        $tz = config('app.timezone', 'Asia/Manila');
        $today = Carbon::now($tz)->startOfDay();
        $cacheKey = 'prayer_times_zamboanga_v13nextclient_'.$today->toDateString();
        $ttl = max(300, (int) config('services.prayer_times.cache_ttl', 3600));

        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        $fresh = $this->fetchForDate($today, $tz);
        if ($fresh !== null) {
            Cache::put($cacheKey, $fresh, $ttl);
        }

        return $fresh;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchForDate(Carbon $date, string $timezone): ?array
    {
        try {
            $data = $this->requestTimingsForDate($date);
            if (! is_array($data)) {
                return null;
            }

            $timings = $data['timings'] ?? null;
            if (! is_array($timings)) {
                return null;
            }

            $meta = is_array($data['meta'] ?? null) ? $data['meta'] : [];
            $methodMeta = is_array($meta['method'] ?? null) ? $meta['method'] : [];
            $methodName = (string) ($methodMeta['name'] ?? '');

            $dateInfo = is_array($data['date'] ?? null) ? $data['date'] : [];
            $readable = (string) ($dateInfo['readable'] ?? $date->format('M j, Y'));
            $hijri = is_array($dateInfo['hijri'] ?? null) ? $dateInfo['hijri'] : [];
            $hijriReadable = null;
            if (isset($hijri['day'], $hijri['month']['en'], $hijri['year'])) {
                $hijriReadable = (string) $hijri['day'].' '.(string) ($hijri['month']['en'] ?? '').' '.(string) $hijri['year'].' AH';
            }

            $order = ['Fajr', 'Sunrise', 'Dhuhr', 'Asr', 'Maghrib', 'Isha'];
            $rows = [];
            foreach ($order as $key) {
                if (! isset($timings[$key])) {
                    continue;
                }
                $at = $this->wallClockOnDate((string) $timings[$key], $date, $timezone);
                $rows[] = [
                    'key' => $key,
                    'time' => $this->formatDisplayTime((string) $timings[$key]),
                    'at_ms' => $at !== null ? $at->getTimestamp() * 1000 : 0,
                ];
            }

            if ($rows === []) {
                return null;
            }

            $tomorrow = $date->copy()->addDay();
            $tomData = $this->requestTimingsForDate($tomorrow);
            $tomTimings = is_array($tomData['timings'] ?? null) ? $tomData['timings'] : [];
            $fajrTomorrowMs = null;
            if (isset($tomTimings['Fajr'])) {
                $fajrAt = $this->wallClockOnDate((string) $tomTimings['Fajr'], $tomorrow, $timezone);
                if ($fajrAt !== null) {
                    $fajrTomorrowMs = $fajrAt->getTimestamp() * 1000;
                }
            }

            $now = Carbon::now($timezone);
            $next = $this->resolveNextPrayer($order, $timings, $date, $timezone, $now, $tomTimings);

            return [
                'city' => (string) config('services.prayer_times.city_label', 'Zamboanga City'),
                'date_readable' => $readable,
                'hijri_readable' => $hijriReadable,
                'method_name' => $methodName,
                'times' => $rows,
                'next' => $next,
                'fajr_tomorrow_ms' => $fajrTomorrowMs,
                'source_url' => (string) config('services.prayer_times.source_url', 'https://aladhan.com'),
                'reference_url' => self::MUSLIMPRO_REFERENCE_URL,
                'fetched_at' => now($timezone),
            ];
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    /**
     * @param  list<string>  $order
     * @param  array<string, mixed>  $timings
     * @param  array<string, mixed>|null  $tomorrowTimings  Pre-fetched timings for the day after {@see $date} (avoids duplicate HTTP).
     * @return array<string, mixed>|null
     */
    private function resolveNextPrayer(array $order, array $timings, Carbon $date, string $timezone, Carbon $now, ?array $tomorrowTimings = null): ?array
    {
        $nextKey = null;
        $nextAt = null;

        foreach ($order as $key) {
            if (! isset($timings[$key])) {
                continue;
            }
            $at = $this->wallClockOnDate((string) $timings[$key], $date, $timezone);
            if ($at !== null && $at->gt($now)) {
                $nextKey = $key;
                $nextAt = $at;
                break;
            }
        }

        if ($nextAt === null) {
            $tomorrow = $date->copy()->addDay();
            $tomTimings = $tomorrowTimings;
            if (! is_array($tomTimings)) {
                $tomData = $this->requestTimingsForDate($tomorrow);
                $tomTimings = is_array($tomData['timings'] ?? null) ? $tomData['timings'] : null;
            }
            if (is_array($tomTimings) && isset($tomTimings['Fajr'])) {
                $nextKey = 'Fajr';
                $nextAt = $this->wallClockOnDate((string) $tomTimings['Fajr'], $tomorrow, $timezone);
            }
        }

        if ($nextKey === null || $nextAt === null) {
            return null;
        }

        $isTomorrow = ! $nextAt->isSameDay($now);

        return [
            'key' => $nextKey,
            'at_ms' => $nextAt->getTimestamp() * 1000,
            'is_tomorrow' => $isTomorrow,
            'countdown' => $this->formatCountdownLabel($now, $nextAt),
        ];
    }

    private function formatCountdownLabel(Carbon $from, Carbon $to): string
    {
        if (! $from->lessThan($to)) {
            return '';
        }

        $total = max(0, $to->getTimestamp() - $from->getTimestamp());
        $hours = intdiv($total, 3600);
        $minutes = intdiv($total % 3600, 60);

        if ($hours > 0) {
            return __('zcstats.prayer_countdown_h_m', [
                'hours' => $hours,
                'minutes' => $minutes,
            ]);
        }

        return __('zcstats.prayer_countdown_m', [
            'minutes' => max(0, $minutes),
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function requestTimingsForDate(Carbon $date): ?array
    {
        $lat = (float) config('services.prayer_times.lat', 6.9214);
        $lon = (float) config('services.prayer_times.lon', 122.079);
        $method = (int) config('services.prayer_times.method', 3);

        $pathDate = $date->format('d-m-Y');
        $url = rtrim((string) config('services.prayer_times.api_base', 'https://api.aladhan.com/v1'), '/').'/timings/'.$pathDate;

        $response = $this->httpClient()->get($url, [
            'latitude' => $lat,
            'longitude' => $lon,
            'method' => $method,
        ]);

        if (! $response->successful()) {
            Log::warning('Aladhan prayer times request failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        }

        $payload = $response->json();
        if (! is_array($payload) || ($payload['code'] ?? null) !== 200) {
            Log::warning('Aladhan prayer times unexpected payload', ['payload' => $payload]);

            return null;
        }

        $data = $payload['data'] ?? null;

        return is_array($data) ? $data : null;
    }

    private function wallClockOnDate(string $raw, Carbon $localDate, string $timezone): ?Carbon
    {
        $clean = $this->normalizeTime($raw);
        if (! preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $clean, $m)) {
            return null;
        }

        return Carbon::createFromDate(
            (int) $localDate->year,
            (int) $localDate->month,
            (int) $localDate->day,
            $timezone
        )->setTime(
            (int) $m[1],
            (int) $m[2],
            isset($m[3]) ? (int) $m[3] : 0
        );
    }

    private function normalizeTime(string $raw): string
    {
        $t = preg_replace('/\s*\([^)]*\)\s*$/', '', trim($raw));

        return is_string($t) ? $t : $raw;
    }

    /**
     * Aladhan returns 24h "H:i" or "H:i:s"; show12-hour with AM/PM for the dashboard.
     */
    private function formatDisplayTime(string $raw): string
    {
        $clean = $this->normalizeTime($raw);
        if (preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $clean, $m)) {
            $t = Carbon::createFromTime(
                (int) $m[1],
                (int) $m[2],
                isset($m[3]) ? (int) $m[3] : 0
            );

            return $t->format('g:i A');
        }

        return $clean;
    }

    private function httpClient(): PendingRequest
    {
        $options = [];
        if (! config('services.prayer_times.verify_ssl', true)) {
            $options['verify'] = false;
        }

        return Http::timeout(15)
            ->withHeaders([
                'Accept' => 'application/json',
            ])
            ->withOptions($options);
    }
}
