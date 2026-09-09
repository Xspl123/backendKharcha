<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class LeadNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public $userName;
    public $title;
    public $body;
    public $leadId;

    /**
     * Create a new message instance. Same (title, body, leadId) shape the
     * push-notification payload already uses everywhere — this Mailable
     * just renders that same content as an email instead of a browser
     * push, so every existing trigger (follow-up reminders, new web
     * leads, workflow rules, duplicate detection) can send both from one
     * payload without duplicating message text in two places.
     */
    public function __construct($userName, $title, $body, $leadId = null)
    {
        $this->userName = $userName;
        $this->title = $title;
        $this->body = $body;
        $this->leadId = $leadId;
    }

    public function build()
    {
        $leadUrl = $this->leadId
            ? rtrim(config('app.frontend_url', config('app.url')), '/') . '/crm/leads/' . $this->leadId
            : null;

        $button = $leadUrl
            ? "<p style='margin-top:20px;'><a href='{$leadUrl}' style='background:#2563EB;color:#fff;padding:10px 18px;border-radius:8px;text-decoration:none;font-weight:600;'>Lead Dekhein</a></p>"
            : '';

        return $this->subject($this->title)
                    ->html("
                        <div style='font-family: Arial, sans-serif; font-size: 15px; color: #333;'>
                            <p>Hello {$this->userName},</p>
                            <p><strong>{$this->title}</strong></p>
                            <p>{$this->body}</p>
                            {$button}
                            <br>
                            <p style='color:#94a3b8;font-size:12px;'>Yeh email CRM ke notification settings se automatically bheji gayi hai.</p>
                            <p>Thank you,<br><strong>" . config('app.name') . "</strong></p>
                        </div>
                    ");
    }
}