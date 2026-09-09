<?php

namespace App\Services;

use App\Mail\LeadNotificationMail;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class NotificationService
{
    public function __construct(private PushNotificationService $push) {}

    /**
     * Send push notification + email notification.
     *
     * @param array{title:string, body:string, data?:array} $payload
     */
    public function sendToUser(int $userId, array $payload): void
    {
        // Push notification should never stop email delivery
        try {
            $this->push->sendToUser($userId, $payload);
        } catch (\Throwable $e) {
            Log::warning('Push notification failed: ' . $e->getMessage());
        }

        $user = User::find($userId);

        if (!$user || empty($user->email)) {
            Log::warning("Notification email skipped for user {$userId}: email not found.");
            return;
        }

        try {
            Mail::to($user->email)->send(
                new LeadNotificationMail(
                    $user->name,
                    $payload['title'] ?? 'Notification',
                    $payload['body'] ?? '',
                    $payload['data']['leadId'] ?? null
                )
            );

            Log::info("Lead notification email sent successfully to {$user->email}");
        } catch (\Throwable $e) {
            Log::error("Lead notification email failed for {$user->email}: " . $e->getMessage());
        }
    }
}