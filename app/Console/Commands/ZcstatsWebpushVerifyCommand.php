<?php

namespace App\Console\Commands;

use Base64Url\Base64Url;
use Illuminate\Console\Command;
use Jose\Component\Core\AlgorithmManager;
use Jose\Component\Core\JWK;
use Jose\Component\Signature\Algorithm\ES256;
use Jose\Component\Signature\JWSVerifier;
use Jose\Component\Signature\Serializer\CompactSerializer;
use Minishlink\WebPush\ContentEncoding;
use Minishlink\WebPush\Utils;
use Minishlink\WebPush\VAPID;

class ZcstatsWebpushVerifyCommand extends Command
{
    protected $signature = 'zcstats:webpush-verify';

    protected $description = 'Verify VAPID keys form a valid pair and JWTs verify (catches many Apple BadJwtToken causes)';

    public function handle(): int
    {
        if (! config('webpush.enabled')) {
            $this->error('Web Push is not enabled. Set WEBPUSH_ENABLED and both VAPID keys in .env.');

            return self::FAILURE;
        }

        try {
            $vapid = VAPID::validate([
                'subject' => (string) config('webpush.subject'),
                'publicKey' => (string) config('webpush.public_key'),
                'privateKey' => (string) config('webpush.private_key'),
            ]);
        } catch (\Throwable $e) {
            $this->error('Invalid VAPID configuration: '.$e->getMessage());
            $this->newLine();
            $this->warn('Check WEBPUSH_VAPID_PUBLIC_KEY / WEBPUSH_VAPID_PRIVATE_KEY: Base64URL, single line, no spaces, not truncated.');

            return self::FAILURE;
        }

        $subject = (string) config('webpush.subject');
        if (! str_starts_with($subject, 'mailto:') && ! preg_match('#^https?://#i', $subject)) {
            $this->warn('WEBPUSH_VAPID_SUBJECT should be mailto:you@example.com or https://your-site.example (RFC 8292).');
        }

        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);
        if (str_starts_with($subject, 'mailto:') && $appHost) {
            $this->comment('Tip: If Safari returns 403 BadJwtToken, try WEBPUSH_VAPID_SUBJECT=https://'.$appHost.' (HTTPS URL, no path, same site users open).');
        }

        try {
            $headers = VAPID::getVapidHeaders(
                'https://web.push.apple.com',
                $subject,
                $vapid['publicKey'],
                $vapid['privateKey'],
                ContentEncoding::aesgcm,
            );
        } catch (\Throwable $e) {
            $this->error('Could not build VAPID headers: '.$e->getMessage());

            return self::FAILURE;
        }

        $authorization = $headers['Authorization'] ?? '';
        if (! str_starts_with($authorization, 'WebPush ')) {
            $this->error('Unexpected Authorization header format from web-push library.');

            return self::FAILURE;
        }

        $jwt = substr($authorization, strlen('WebPush '));
        $jws = (new CompactSerializer)->unserialize($jwt);

        [$x, $y] = Utils::unserializePublicKey($vapid['publicKey']);
        $publicJwk = new JWK([
            'kty' => 'EC',
            'crv' => 'P-256',
            'x' => Base64Url::encode($x),
            'y' => Base64Url::encode($y),
        ]);

        $verifier = new JWSVerifier(new AlgorithmManager([new ES256]));
        if (! $verifier->verifyWithKey($jws, $publicJwk, 0)) {
            $this->error('VAPID public and private keys do not form a valid signing pair.');
            $this->newLine();
            $this->warn('Regenerate keys (zcstats:webpush-vapid or npx web-push generate-vapid-keys), update .env in one paste, redeploy, then clear push_subscriptions and have devices subscribe again.');

            return self::FAILURE;
        }

        $this->info('VAPID keys are a valid pair; sample JWT verifies for audience https://web.push.apple.com');
        $this->newLine();
        $hintHost = $appHost ? 'https://'.$appHost.' (no path)' : 'an https:// URL matching your live site';
        $this->comment('If Apple still returns 403 BadJwtToken: set WEBPUSH_VAPID_SUBJECT='.$hintHost.', ensure server time is correct (NTP), and remove stale push_subscriptions so clients re-subscribe with this public key.');

        return self::SUCCESS;
    }
}
