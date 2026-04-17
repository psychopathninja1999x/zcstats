<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

class ZcstatsWebpushVapidCommand extends Command
{
    protected $signature = 'zcstats:webpush-vapid';

    protected $description = 'Print a new VAPID public/private key pair and suggested .env lines for Web Push';

    public function handle(): int
    {
        try {
            $keys = VAPID::createVapidKeys();
        } catch (\Throwable $e) {
            $this->error('PHP could not generate EC keys (OpenSSL). Common on some Windows PHP builds.');
            $this->newLine();
            $this->line('Generate keys with Node instead:');
            $this->line('  npx --yes web-push generate-vapid-keys');
            $this->newLine();
            $this->line('Then set WEBPUSH_VAPID_PUBLIC_KEY, WEBPUSH_VAPID_PRIVATE_KEY, WEBPUSH_VAPID_SUBJECT=mailto:..., WEBPUSH_ENABLED=true');

            return self::FAILURE;
        }

        $this->line('Add these to your .env (keep the private key secret):');
        $this->newLine();
        $this->line('WEBPUSH_ENABLED=true');
        $this->line('WEBPUSH_VAPID_PUBLIC_KEY='.$keys['publicKey']);
        $this->line('WEBPUSH_VAPID_PRIVATE_KEY='.$keys['privateKey']);
        $this->line('WEBPUSH_VAPID_SUBJECT=mailto:you@example.com');
        $this->newLine();
        $this->comment('Safari / Apple push: if you later see 403 BadJwtToken, try WEBPUSH_VAPID_SUBJECT=https://your-production-host (no path).');
        $this->comment('Then run: php artisan migrate && php artisan zcstats:webpush-verify');

        return self::SUCCESS;
    }
}
