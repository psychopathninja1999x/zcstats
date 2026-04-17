<?php

namespace App\Console\Commands;

use App\Models\PushSubscription;
use App\Services\LiveDigestService;
use App\Services\ZcWebPushService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class ZcstatsPushLiveDigestCommand extends Command
{
    protected $signature = 'zcstats:push-live-digest';

    protected $description = 'Send Web Push notifications when the live dashboard digest hash changes';

    public function handle(LiveDigestService $liveDigest, ZcWebPushService $webPush): int
    {
        if (! config('webpush.enabled')) {
            return self::SUCCESS;
        }

        $hash = $liveDigest->hash();
        $cacheKey = 'zcstats_push_last_live_digest';
        $prev = Cache::get($cacheKey);

        if ($prev === null) {
            Cache::forever($cacheKey, $hash);

            return self::SUCCESS;
        }

        if ($prev === $hash) {
            return self::SUCCESS;
        }

        $subs = PushSubscription::query()->where('wants_live', true)->get();
        if ($subs->isEmpty()) {
            Cache::forever($cacheKey, $hash);

            return self::SUCCESS;
        }

        $baseUrl = rtrim((string) config('app.url'), '/').'/';

        $webPush->sendToMany($subs, function (PushSubscription $sub) use ($hash, $baseUrl): array {
            $locale = in_array($sub->locale, ['en', 'tl', 'cbk', 'gly'], true) ? $sub->locale : 'en';
            app()->setLocale($locale);

            return [
                'title' => __('zcstats.notify_live_title'),
                'body' => __('zcstats.notify_live_body'),
                'url' => $baseUrl,
                'tag' => 'zc-live-'.substr($hash, 0, 24),
            ];
        });

        Cache::forever($cacheKey, $hash);

        return self::SUCCESS;
    }
}
