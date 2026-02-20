<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public $otp;

    public function __construct($otp)
    {
        $this->otp = $otp;
    }

        public function build()
{
    return $this->subject('Email Verification OTP')
        ->html("
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>Email Verification</title>
        </head>
        <body style='margin:0;padding:0;background-color:#f4f6f9;font-family:Arial,sans-serif;'>
            <table width='100%' cellpadding='0' cellspacing='0' style='padding:20px;'>
                <tr>
                    <td align='center'>
                        <table width='500' cellpadding='0' cellspacing='0' 
                               style='background:#ffffff;border-radius:8px;padding:30px;text-align:center;'>

                            <tr>
                                <td>
                                    <h1 style='color:#1976d2;'>ApnaKharcha</h1>
                                    <h2 style='color:#2c3e50;margin-bottom:10px;'>
                                        Email Verification
                                    </h2>
                                    <p style='color:#555;font-size:14px;margin-bottom:25px;'>
                                        Use the OTP below to verify your email address.
                                    </p>

                                    <div style='font-size:32px;
                                                font-weight:bold;
                                                letter-spacing:5px;
                                                color:#ffffff;
                                                background:#1976d2;
                                                padding:15px 20px;
                                                border-radius:6px;
                                                display:inline-block;
                                                margin-bottom:25px;'>
                                        {$this->otp}
                                    </div>

                                    <p style='color:#777;font-size:13px;'>
                                        This OTP is valid for 10 minutes.
                                    </p>

                                    <hr style='margin:30px 0;border:none;border-top:1px solid #eee;'>

                                    <p style='font-size:12px;color:#aaa;'>
                                        If you did not request this, please ignore this email.
                                    </p>
                                </td>
                            </tr>

                        </table>
                    </td>
                </tr>
            </table>
        </body>
        </html>
        ");
}
}