<?php

namespace App\Services;

use App\Models\PushSubscription;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class PushNotificationService
{
    private function client(): WebPush
    {
        return new WebPush([
            'VAPID' => [
                'subject'    => config('services.webpush.subject'),
                'publicKey'  => config('services.webpush.public_key'),
                'privateKey' => config('services.webpush.private_key'),
            ],
        ]);
    }

    /**
     * @param int $userId User to notify (all of their registered browsers/devices)
     * @param array{title:string, body:string, data?:array} $payload
     */
    public function sendToUser(int $userId, array $payload): void
    {
        $subscriptions = PushSubscription::where('user_id', $userId)->get();
        if ($subscriptions->isEmpty()) return;

        $webPush = $this->client();
        $payloadJson = json_encode($payload);

        foreach ($subscriptions as $sub) {
            $webPush->queueNotification(
                Subscription::create([
                    'endpoint'        => $sub->endpoint,
                    'publicKey'       => $sub->public_key,
                    'authToken'       => $sub->auth_token,
                    'contentEncoding' => $sub->content_encoding,
                ]),
                $payloadJson
            );
        }

        foreach ($webPush->flush() as $report) {
            if ($report->isSuccess()) continue;

            // 404/410 = browser says this subscription is dead (user
            // revoked permission, uninstalled, cleared site data, etc.) —
            // clean it up so we stop trying to push to it every run.
            $statusCode = $report->getResponse()?->getStatusCode();
            if (in_array($statusCode, [404, 410], true)) {
                $endpoint = $report->getRequest()->getUri()->__toString();
                PushSubscription::where('endpoint', $endpoint)->delete();
            }
        }
    }
}
