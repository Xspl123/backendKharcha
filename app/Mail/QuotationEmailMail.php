<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Queue\SerializesModels;

class QuotationEmailMail extends Mailable
{
    use Queueable, SerializesModels;

    public $senderName;
    public $subjectLine;
    public $bodyText;
    public $quotationNo;
    public $pdfBase64;
    public $pdfFilename;

    /**
     * $pdfBase64 is the raw base64 payload (no "data:application/pdf;base64,"
     * prefix — the frontend strips that before sending, since the PDF
     * itself is generated client-side via html2pdf, same as the existing
     * Invoice PDF feature).
     */
    public function __construct($senderName, $subjectLine, $bodyText, $quotationNo, $pdfBase64, $pdfFilename)
    {
        $this->senderName = $senderName;
        $this->subjectLine = $subjectLine;
        $this->bodyText = $bodyText;
        $this->quotationNo = $quotationNo;
        $this->pdfBase64 = $pdfBase64;
        $this->pdfFilename = $pdfFilename;
    }

    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => base64_decode($this->pdfBase64), $this->pdfFilename)
                ->withMime('application/pdf'),
        ];
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
<div style="color:#ffffff; font-size:17px; font-weight:700;">{$appName}</div>
<div style="color:#c7d2fe; font-size:12px;">Quotation {$this->quotationNo}</div>
</td>
</tr>

<tr>
<td style="padding: 32px;">
<div style="font-size:15px; color:#334155; line-height:1.7;">
{$safeBody}
</div>
<table role="presentation" cellpadding="0" cellspacing="0" style="margin-top:20px; background:#eff6ff; border-radius:10px;">
<tr><td style="padding:14px 18px; font-size:13px; color:#1d4ed8;">
&#128206; Quotation PDF attached ({$this->quotationNo})
</td></tr>
</table>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:28px; border-top:1px solid #e2e8f0; padding-top:20px;">
<tr>
<td>
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
&copy; {$year} {$appName}
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
}