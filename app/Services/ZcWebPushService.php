<?php

namespace App\Services;

use App\Models\PushSubscription;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class ZcWebPushService
{
    public function isConfigured(): bool
    {
        return (bool) config('webpush.enabled');
    }

    public function makeClient(): WebPush
    {
        return new WebPush(
            [
                'VAPID' => [
                    'subject' => (string) config('webpush.subject'),
                    'publicKey' => (string) config('webpush.public_key'),
                    'privateKey' => (string) config('webpush.private_key'),
                ],
            ],
            [],
            30,
            $this->guzzleClientOptions(),
        );
    }

    /**
     * Guzzle options for outbound HTTPS to FCM / browser push services.
     *
     * @return array<string, mixed>
     */
    private function guzzleClientOptions(): array
    {
        $path = config('webpush.curl_ca_bundle');
        if (! is_string($path) || $path === '') {
            return [];
        }

        $resolved = $this->resolveReadablePath($path);
        if ($resolved === null) {
            return [];
        }

        return ['verify' => $resolved];
    }

    private function resolveReadablePath(string $path): ?string
    {
        $candidates = [$path];
        if (! $this->isAbsoluteFilesystemPath($path)) {
            $candidates[] = base_path($path);
        }

        foreach ($candidates as $candidate) {
            if (is_readable($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function isAbsoluteFilesystemPath(string $path): bool
    {
        if (str_starts_with($path, '/')) {
            return true;
        }

        return strlen($path) > 2
            && ctype_alpha($path[0])
            && $path[1] === ':'
            && ($path[2] === '\\' || $path[2] === '/');
    }

    /**
     * @param  iterable<int, PushSubscription>  $subscriptions
     * @param  callable(PushSubscription): array<string, mixed>  $payloadFor
     * @return array{success: int, failed: int, errors: list<array{endpoint: string, reason: string}>}
     */
    public function sendToMany(iterable $subscriptions, callable $payloadFor): array
    {
        $empty = ['success' => 0, 'failed' => 0, 'errors' => []];

        if (! $this->isConfigured()) {
            return $empty;
        }

        $webPush = $this->makeClient();
        /** @var list<PushSubscription> $models */
        $models = [];

        foreach ($subscriptions as $model) {
            if (! $model instanceof PushSubscription) {
                continue;
            }

            $payload = $payloadFor($model);
            $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
            if ($json === false) {
                continue;
            }

            $subscription = Subscription::create([
                'endpoint' => $model->endpoint,
                'keys' => [
                    'p256dh' => $model->public_key,
                    'auth' => $model->auth_token,
                ],
                'contentEncoding' => $model->content_encoding ?: 'aesgcm',
            ]);

            $webPush->queueNotification($subscription, $json);
            $models[] = $model;
        }

        if ($models === []) {
            return $empty;
        }

        $success = 0;
        $failed = 0;
        /** @var list<array{endpoint: string, reason: string}> $errors */
        $errors = [];
        $idx = 0;

        foreach ($webPush->flush() as $report) {
            $model = $models[$idx] ?? null;
            $idx++;

            if ($model !== null && $report->isSubscriptionExpired()) {
                $model->delete();
            }

            if ($report->isSuccess()) {
                $success++;
            } else {
                $failed++;
                $endpoint = $model !== null
                    ? (strlen($model->endpoint) > 120 ? substr($model->endpoint, 0, 120).'…' : $model->endpoint)
                    : $report->getEndpoint();
                $errors[] = [
                    'endpoint' => $endpoint,
                    'reason' => $report->getReason(),
                ];
            }
        }

        return ['success' => $success, 'failed' => $failed, 'errors' => $errors];
    }
}
