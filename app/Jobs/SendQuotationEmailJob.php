<?php

namespace App\Jobs;

use App\Mail\QuotationEmailMail;
use App\Models\Quotation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendQuotationEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Retry failed jobs 3 times.
     */
    public $tries = 3;

    /**
     * Timeout after 120 seconds.
     */
    public $timeout = 120;

    public function __construct(
        public int $quotationId,
        public string $senderName,
        public ?string $replyToEmail,
        public string $to,
        public string $subject,
        public string $body,
        public string $pdfBase64,
        public ?array $cc = null
    ) {
        // Email jobs alag queue me jayengi.
        $this->onQueue('emails');
    }

    public function handle(): void
    {
        $quotation = Quotation::find($this->quotationId);

        if (! $quotation) {
            Log::warning('Quotation Email Job: Quotation not found.', [
                'quotation_id' => $this->quotationId,
            ]);
            return;
        }

        $mail = Mail::to($this->to);

        // Optional CC
        if (! empty($this->cc)) {
            $mail->cc($this->cc);
        }

        $mailable = new QuotationEmailMail(
            $this->senderName,
            $this->subject,
            $this->body,
            $quotation->quotation_no,
            $this->pdfBase64,
            "Quotation_{$quotation->quotation_no}.pdf"
        );

        // Reply-To sender
        if (! empty($this->replyToEmail)) {
            $mailable->replyTo($this->replyToEmail, $this->senderName);
        }

        $mail->send($mailable);

        Log::info('Quotation email sent successfully.', [
            'quotation_id' => $quotation->id,
            'quotation_no' => $quotation->quotation_no,
            'to'           => $this->to,
        ]);
    }

    /**
     * Called after all retries fail.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Quotation Email Job Failed.', [
            'quotation_id' => $this->quotationId,
            'to'           => $this->to,
            'error'        => $exception->getMessage(),
        ]);
    }
}