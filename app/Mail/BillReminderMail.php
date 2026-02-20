<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class BillReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $category;

    /**
     * Create a new message instance.
     */
    public function __construct($user, $category)
    {
        $this->user = $user;
        $this->category = $category;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->subject('Bill Reminder: ' . $this->category->name)
                    ->html("
                        <div style='font-family: Arial, sans-serif; font-size: 15px; color: #333;'>
                            <p>Hello {$this->user->name},</p>
                            <p>This is a friendly reminder about your upcoming <strong>{$this->category->name}</strong> bill.</p>
                            <p>Please make sure to complete the payment on time.</p>
                            <br>
                            <p>Thank you,<br><strong>" . config('app.name') . "</strong></p>
                        </div>
                    ");
    }
}
