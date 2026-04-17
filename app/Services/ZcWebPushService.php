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
        return new WebPush([
            'VAPID' => [
                'subject' => (string) config('webpush.subject'),
                'publicKey' => (string) config('webpush.public_key'),
                'privateKey' => (string) config('webpush.private_key'),
            ],
        ]);
    }

    /**
     * @param  iterable<int, PushSubscription>  $subscriptions
     * @param  callable(PushSubscription): array<string, mixed>  $payloadFor
     */
    public function sendToMany(iterable $subscriptions, callable $payloadFor): void
    {
        if (! $this->isConfigured()) {
            return;
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
            return;
        }

        $idx = 0;
        foreach ($webPush->flush() as $report) {
            $model = $models[$idx] ?? null;
            $idx++;
            if ($model !== null && $report->isSubscriptionExpired()) {
                $model->delete();
            }
        }
    }
}
