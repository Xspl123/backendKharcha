<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PushSubscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PushSubscriptionController extends Controller
{
    // GET /api/push/vapid-public-key
    // The public key is safe to expose — it's only useful for the browser
    // to identify who signed the push, not for sending pushes itself.
    public function vapidPublicKey()
    {
        return response()->json(['public_key' => config('services.webpush.public_key')]);
    }

    // POST /api/push/subscribe
    // Body: { endpoint, keys: { p256dh, auth } }  ← this is exactly the
    // shape browsers hand back from PushManager.subscribe().
    public function subscribe(Request $request)
    {
        $data = $request->validate([
            'endpoint'      => 'required|string',
            'keys.p256dh'   => 'required|string',
            'keys.auth'     => 'required|string',
            'content_encoding' => 'nullable|string|in:aesgcm,aes128gcm',
        ]);

        $user = Auth::user();
        $endpointHash = hash('sha256', $data['endpoint']);

        $subscription = PushSubscription::updateOrCreate(
            ['user_id' => $user->id, 'endpoint_hash' => $endpointHash],
            [
                'org_id'           => $user->org_id,
                'endpoint'         => $data['endpoint'],
                'public_key'       => $data['keys']['p256dh'],
                'auth_token'       => $data['keys']['auth'],
                'content_encoding' => $data['content_encoding'] ?? 'aesgcm',
            ]
        );

        return response()->json(['message' => 'Subscribed successfully.', 'data' => $subscription], 201);
    }

    // POST /api/push/unsubscribe
    // Body: { endpoint }
    public function unsubscribe(Request $request)
    {
        $data = $request->validate(['endpoint' => 'required|string']);

        PushSubscription::where('user_id', Auth::id())
            ->where('endpoint_hash', hash('sha256', $data['endpoint']))
            ->delete();

        return response()->json(['message' => 'Unsubscribed successfully.']);
    }
}
