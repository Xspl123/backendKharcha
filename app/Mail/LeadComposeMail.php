<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class LeadComposeMail extends Mailable
{
    use Queueable, SerializesModels;

    public $senderName;
    public $subjectLine;
    public $bodyText;

    /**
     * A plain, sales-rep-composed email — not a system notification
     * template like LeadNotificationMail. $bodyText is escaped + turned
     * into <br> line breaks (not rendered as raw HTML) since it's
     * free-form user input from the "Send Email" panel, not app-generated
     * content.
     */
    public function __construct($senderName, $subjectLine, $bodyText)
    {
        $this->senderName = $senderName;
        $this->subjectLine = $subjectLine;
        $this->bodyText = $bodyText;
    }

    public function build()
    {
        $safeBody = nl2br(e($this->bodyText));
        $appName = config('app.name');
        $year = date('Y');

        $html = <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"></head>
<body style="margin:0; padding:0; background-color:#f1f5f9; font-family: 'Segoe UI', Arial, sans-serif;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f1f5f9; padding: 32px 16px;">
<tr><td align="center">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px; background-color:#ffffff; border-radius:16px; overflow:hidden; box-shadow: 0 4px 24px rgba(15,23,42,0.08);">

<tr>
<td style="background: linear-gradient(135deg,#1e3a8a,#2563EB); padding: 28px 32px;">
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
<div style="color:#ffffff; font-size:17px; font-weight:700;">{$appName}</div>
<div style="color:#c7d2fe; font-size:12px;">Ek naya message aapke liye</div>
</td>
</tr>
</table>
</td>
</tr>

<tr>
<td style="padding: 32px;">
<div style="font-size:15px; color:#334155; line-height:1.7;">
{$safeBody}
</div>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:28px; border-top:1px solid #e2e8f0; padding-top:20px;">
<tr>
<td style="width:36px; vertical-align:top;">
<table role="presentation" cellpadding="0" cellspacing="0" style="width:32px;height:32px;background:#eef2ff;border-radius:8px;">
<tr><td align="center" valign="middle" style="color:#4338ca;font-size:14px;font-weight:700;">
{$this->senderInitial()}
</td></tr>
</table>
</td>
<td style="padding-left:10px;">
<div style="font-size:14px; font-weight:700; color:#1e293b;">{$this->senderName}</div>
<div style="font-size:12px; color:#94a3b8;">{$appName}</div>
</td>
</tr>
</table>
</td>
</tr>

<tr>
<td style="background-color:#f8fafc; padding:18px 32px; border-top:1px solid #e2e8f0;">
<div style="font-size:11px; color:#94a3b8; text-align:center;">
&copy; {$year} {$appName} &middot; Yeh email seedha CRM se bheji gayi hai
</div>
</td>
</tr>

</table>
</td></tr>
</table>
</body>
</html>
HTML;

        return $this->subject($this->subjectLine)->html($html);
    }

    private function brandInitial(): string
    {
        return strtoupper(substr(config('app.name', 'A'), 0, 1));
    }

    private function senderInitial(): string
    {
        return strtoupper(substr(trim((string) $this->senderName) ?: 'U', 0, 1));
    }
}