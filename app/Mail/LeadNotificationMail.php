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
        $appName = config('app.name');
        $year = date('Y');
        $bodyHtml = nl2br(e($this->body));

        $button = '';
        if ($leadUrl) {
            $button = <<<BTN
<table role="presentation" cellpadding="0" cellspacing="0" style="margin-top:24px;">
<tr><td style="background:#2563EB; border-radius:10px;">
<a href="{$leadUrl}" style="display:inline-block; padding:12px 24px; color:#ffffff; text-decoration:none; font-weight:700; font-size:14px;">Lead Dekhein &rarr;</a>
</td></tr>
</table>
BTN;
        }

        $html = <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"></head>
<body style="margin:0; padding:0; background-color:#f1f5f9; font-family: 'Segoe UI', Arial, sans-serif;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f1f5f9; padding: 32px 16px;">
<tr><td align="center">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px; background-color:#ffffff; border-radius:16px; overflow:hidden; box-shadow: 0 4px 24px rgba(15,23,42,0.08);">

<tr>
<td style="background: linear-gradient(135deg,#1e3a8a,#2563EB); padding: 24px 32px;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0">
<tr>
<td style="width:44px;">
<table role="presentation" cellpadding="0" cellspacing="0" style="width:40px;height:40px;background:#ffffff;border-radius:10px;">
<tr><td align="center" valign="middle" style="color:#2563EB;font-size:20px;font-weight:800;">
{$this->brandInitial()}
</td></tr>
</table>
</td>
<td style="padding-left:12px;">
<div style="color:#ffffff; font-size:16px; font-weight:700;">{$appName}</div>
<div style="color:#c7d2fe; font-size:11px; text-transform:uppercase; letter-spacing:0.5px;">CRM Notification</div>
</td>
</tr>
</table>
</td>
</tr>

<tr>
<td style="padding: 32px 32px 8px 32px;">
<span style="display:inline-block; background:#eef2ff; color:#4338ca; font-size:11px; font-weight:700; padding:4px 10px; border-radius:20px; text-transform:uppercase; letter-spacing:0.5px;">
Notification
</span>
</td>
</tr>

<tr>
<td style="padding: 12px 32px 32px 32px;">
<p style="margin:0 0 8px 0; font-size:14px; color:#64748b;">Hello {$this->userName},</p>
<h2 style="margin:0 0 12px 0; font-size:19px; color:#0f172a; font-weight:700;">{$this->title}</h2>
<div style="font-size:15px; color:#334155; line-height:1.7;">{$bodyHtml}</div>
{$button}
</td>
</tr>

<tr>
<td style="background-color:#f8fafc; padding:18px 32px; border-top:1px solid #e2e8f0;">
<div style="font-size:11px; color:#94a3b8; text-align:center;">
&copy; {$year} {$appName} &middot; Yeh notification automatically bheji gayi hai
</div>
</td>
</tr>

</table>
</td></tr>
</table>
</body>
</html>
HTML;

        return $this->subject($this->title)->html($html);
    }

    private function brandInitial(): string
    {
        return strtoupper(substr(config('app.name', 'A'), 0, 1));
    }
}