<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use App\Models\Reminder;            // Reminder model jisme due_date, user_id, category_id etc honge
use App\Mail\BillReminderMail;

class SendReminders extends Command
{
    protected $signature = 'app:send-reminders';

    protected $description = 'Send bill reminders 10 days before due date to users';

    public function handle()
    {
        $this->info('SendReminders command started.');  // Debug message

        $targetDate = \Carbon\Carbon::today()->addDays(10);

        $reminders = \App\Models\Reminder::with('user', 'category')
            ->where('due_date', $targetDate)
            ->where('is_sent', false)
            ->get();

        $this->info('Reminders count: ' . $reminders->count());

        foreach ($reminders as $reminder) {
            \Mail::to($reminder->user->email)->send(
                new \App\Mail\BillReminderMail($reminder)
            );

            $reminder->update(['is_sent' => true]);
            $this->info('Reminder sent for ID: ' . $reminder->id);
        }

        $this->info('SendReminders command finished.');
    }

}
