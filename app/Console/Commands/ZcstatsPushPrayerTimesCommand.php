<?php

namespace App\Console\Commands;

use App\Models\PushSubscription;
use App\Services\PrayerTimesService;
use App\Services\ZcWebPushService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class ZcstatsPushPrayerTimesCommand extends Command
{
    protected $signature = 'zcstats:push-prayer-times';

    protected $description = 'Send Web Push at each salāh time (Fajr, Dhuhr, Asr, Maghrib, Isha) for subscribers';

    public function handle(PrayerTimesService $prayerTimes, ZcWebPushService $webPush): int
    {
        if (! config('webpush.enabled')) {
            return self::SUCCESS;
        }

        if (! config('services.prayer_times.enabled', true)) {
            return self::SUCCESS;
        }

        $subs = PushSubscription::query()->where('wants_prayer', true)->get();
        if ($subs->isEmpty()) {
            return self::SUCCESS;
        }

        $data = $prayerTimes->getDashboardData();
        if (! is_array($data)) {
            return self::SUCCESS;
        }

        $tz = (string) config('app.timezone');
        $now = now($tz);
        $baseUrl = rtrim((string) config('app.url'), '/').'/';

        $candidates = [];

        foreach ($data['times'] ?? [] as $row) {
            if (! is_array($row)) {
                continue;
            }
            $key = (string) ($row['key'] ?? '');
            if ($key === '' || $key === 'Sunrise') {
                continue;
            }
            $atMs = (int) ($row['at_ms'] ?? 0);
            if ($atMs <= 0) {
                continue;
            }
            $at = Carbon::createFromTimestampMs($atMs)->timezone($tz);
            $diffSec = $now->getTimestamp() - $at->getTimestamp();
            if ($diffSec >= -45 && $diffSec <= 90) {
                $candidates[] = ['key' => $key, 'at' => $at, 'at_ms' => $atMs];
            }
        }

        $fajrMs = (int) ($data['fajr_tomorrow_ms'] ?? 0);
        if ($fajrMs > 0) {
            $at = Carbon::createFromTimestampMs($fajrMs)->timezone($tz);
            $diffSec = $now->getTimestamp() - $at->getTimestamp();
            if ($diffSec >= -45 && $diffSec <= 90) {
                $candidates[] = ['key' => 'Fajr', 'at' => $at, 'at_ms' => $fajrMs];
            }
        }

        foreach ($candidates as $c) {
            $cacheKey = sprintf(
                'zcstats_prayer_push_%s_%s_%s',
                $c['at']->format('Y-m-d'),
                $c['key'],
                (string) $c['at_ms']
            );

            if (Cache::has($cacheKey)) {
                continue;
            }

            Cache::put($cacheKey, true, 86400 * 2);

            $prayerKey = $c['key'];
            $atMs = $c['at_ms'];

            $webPush->sendToMany($subs, function (PushSubscription $sub) use ($prayerKey, $atMs, $baseUrl): array {
                $locale = in_array($sub->locale, ['en', 'tl', 'cbk', 'gly'], true) ? $sub->locale : 'en';
                app()->setLocale($locale);
                $label = __('zcstats.prayer_'.$prayerKey);

                return [
                    'title' => __('zcstats.notify_prayer_title'),
                    'body' => __('zcstats.notify_prayer_body', ['prayer' => $label]),
                    'url' => $baseUrl.'#prayer',
                    'tag' => 'zc-prayer-'.$prayerKey.'-'.$atMs,
                ];
            });
        }

        return self::SUCCESS;
    }
}
