<?php

namespace App\Http\Controllers;

use App\Models\PushSubscription;
use App\Services\WebPushService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Throwable;

class PushSubscriptionController extends Controller
{
    public function publicKey(WebPushService $push)
    {
        try {
            return response()->json(['publicKey' => $push->publicKey()]);
        } catch (Throwable $e) {
            return response()->json(['message' => 'Push notifications are not available on this server.'], 503);
        }
    }

    public function store(Request $r)
    {
        $data = $r->validate([
            'endpoint' => 'required|url:https|max:2048',
            'keys' => 'required|array',
            'keys.p256dh' => 'required|string|max:1024',
            'keys.auth' => 'required|string|max:1024',
            'contentEncoding' => 'nullable|string|in:aes128gcm,aesgcm',
        ]);

        $subscription = PushSubscription::updateOrCreate(
            ['endpoint_hash' => hash('sha256', $data['endpoint'])],
            [
                'user_id' => $r->user()->id,
                'endpoint' => $data['endpoint'],
                'public_key' => $data['keys']['p256dh'],
                'auth_token' => $data['keys']['auth'],
                'content_encoding' => $data['contentEncoding'] ?? 'aes128gcm',
                'user_agent' => mb_substr((string) $r->userAgent(), 0, 255),
                'last_used_at' => now(),
            ]
        );

        return response()->json(['ok' => true, 'id' => $subscription->id]);
    }

    public function destroy(Request $r)
    {
        $data = $r->validate(['endpoint' => 'required|string|max:2048']);

        PushSubscription::where('user_id', $r->user()->id)
            ->where('endpoint_hash', hash('sha256', $data['endpoint']))
            ->delete();

        return response()->json(['ok' => true]);
    }

    public function test(Request $r, WebPushService $push)
    {
        if (! $r->user()->pushSubscriptions()->exists()) {
            return response()->json(['message' => 'Enable push notifications on this device first.'], 422);
        }

        $result = $push->sendTest($r->user());
        if ($result['sent'] === 0) {
            return response()->json(['message' => 'The test could not be delivered. Re-enable notifications and try again.'], 422);
        }

        return response()->json(['ok' => true, 'sent' => $result['sent']]);
    }
}
