<?php

namespace App\Console\Commands;

use App\Models\PushSubscription;
use App\Services\ZcWebPushService;
use Illuminate\Console\Command;

class ZcstatsPushTestCommand extends Command
{
    protected $signature = 'zcstats:push-test
 {--message= : Custom notification body (optional)}';

    protected $description = 'Send a test Web Push immediately to every stored subscription';

    public function handle(ZcWebPushService $webPush): int
    {
        if (! config('webpush.enabled')) {
            $this->error('Web Push is not enabled. Set WEBPUSH_ENABLED and VAPID keys in .env.');

            return self::FAILURE;
        }

        $subs = PushSubscription::query()->get();
        if ($subs->isEmpty()) {
            $this->warn('No subscriptions yet. On the site: open the bell → choose options → Allow notifications.');

            return self::FAILURE;
        }

        $baseUrl = rtrim((string) config('app.url'), '/').'/';
        $customBody = $this->option('message');
        $tag = 'zc-test-'.bin2hex(random_bytes(6));

        $result = $webPush->sendToMany($subs, function (PushSubscription $sub) use ($baseUrl, $customBody, $tag): array {
            $locale = in_array($sub->locale, ['en', 'tl', 'cbk', 'gly'], true) ? $sub->locale : 'en';
            app()->setLocale($locale);

            return [
                'title' => __('zcstats.notify_push_test_title'),
                'body' => $customBody !== null && $customBody !== ''
                    ? (string) $customBody
                    : __('zcstats.notify_push_test_body'),
                'url' => $baseUrl,
                'tag' => $tag,
            ];
        });

        if ($result['success'] > 0) {
            $this->info("Push service accepted delivery for {$result['success']} subscription(s).");
            $this->comment('If no banner appears: allow site notifications in the browser, turn off Do Not Disturb, try a foreground tab once, and on iOS use the app added to the Home Screen.');
        }

        if ($result['failed'] > 0) {
            $this->newLine();
            $this->warn("Failed for {$result['failed']} subscription(s) (often VAPID keys changed, wrong APP_URL origin, or stale subscription).");
            foreach ($result['errors'] as $err) {
                $this->line('  <fg=yellow>'.$err['endpoint'].'</>');
                $this->line('  <fg=red>'.$err['reason'].'</>');
            }
        }

        if ($result['success'] === 0 && $result['failed'] > 0) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
