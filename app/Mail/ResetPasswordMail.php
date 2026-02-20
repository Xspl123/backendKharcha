<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ResetPasswordMail extends Mailable
{
    use Queueable, SerializesModels;

    public $resetLink;

    public function __construct($resetLink)
    {
        $this->resetLink = $resetLink;
    }

    public function build()
    {
        return $this->subject('Reset Your Password')
                    ->html("
                        <p>Hello,</p>
                        <p>Click the link below to reset your password:</p>
                        <a href='{$this->resetLink}'>{$this->resetLink}</a>
                        <p>If you did not request a password reset, please ignore this email.</p>
                    ");
    }
}