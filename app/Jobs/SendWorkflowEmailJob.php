<?php

namespace App\Jobs;

use App\Mail\LeadNotificationMail;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendWorkflowEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 120;

    public function __construct(
        public int $userId,
        public string $userName,
        public string $email,
        public string $title,
        public string $body,
        public ?int $leadId = null
    ) {
        $this->onQueue('emails');
    }

    public function handle(): void
    {
        Mail::to($this->email)->send(
            new LeadNotificationMail(
                $this->userName,
                $this->title,
                $this->body,
                $this->leadId
            )
        );

        Log::info("Workflow email sent to {$this->email}");
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("Workflow email failed", [
            'email' => $this->email,
            'error' => $exception->getMessage(),
        ]);
    }
}