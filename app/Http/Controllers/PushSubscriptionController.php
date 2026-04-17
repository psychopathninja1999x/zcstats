<?php

namespace App\Http\Controllers;

use App\Http\Requests\PushSubscriptionRequest;
use App\Models\PushSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PushSubscriptionController extends Controller
{
    public function vapidPublicKey(): JsonResponse
    {
        if (! config('webpush.enabled')) {
            return response()->json(['enabled' => false, 'publicKey' => null], 200);
        }

        return response()->json([
            'enabled' => true,
            'publicKey' => config('webpush.public_key'),
        ]);
    }

    public function store(PushSubscriptionRequest $request): JsonResponse
    {
        if (! config('webpush.enabled')) {
            return response()->json(['ok' => false, 'message' => 'Web Push is not configured.'], 503);
        }

        $data = $request->validated();
        $sub = $data['subscription'];
        $endpoint = (string) $sub['endpoint'];
        $keys = $sub['keys'];

        PushSubscription::query()->updateOrCreate(
            ['endpoint' => $endpoint],
            [
                'public_key' => (string) $keys['p256dh'],
                'auth_token' => (string) $keys['auth'],
                'content_encoding' => 'aesgcm',
                'wants_prayer' => (bool) $request->boolean('wants_prayer'),
                'wants_live' => (bool) $request->boolean('wants_live'),
                'locale' => (string) ($data['locale'] ?? app()->getLocale()),
            ]
        );

        return response()->json(['ok' => true]);
    }

    public function update(PushSubscriptionRequest $request): JsonResponse
    {
        if (! config('webpush.enabled')) {
            return response()->json(['ok' => false, 'message' => 'Web Push is not configured.'], 503);
        }

        $data = $request->validated();
        $sub = $data['subscription'];
        $endpoint = (string) $sub['endpoint'];

        $row = PushSubscription::query()->where('endpoint', $endpoint)->first();
        if ($row === null) {
            return response()->json(['ok' => false, 'message' => 'Unknown subscription.'], 404);
        }

        $row->update([
            'wants_prayer' => (bool) $request->boolean('wants_prayer'),
            'wants_live' => (bool) $request->boolean('wants_live'),
            'locale' => (string) ($data['locale'] ?? $row->locale),
        ]);

        return response()->json(['ok' => true]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $request->validate([
            'endpoint' => ['required', 'string', 'max:2048'],
        ]);

        PushSubscription::query()->where('endpoint', $request->string('endpoint'))->delete();

        return response()->json(['ok' => true]);
    }
}
