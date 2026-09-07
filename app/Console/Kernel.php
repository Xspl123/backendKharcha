<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('app:send-reminders')->dailyAt('09:00');
        $schedule->command('dashboard:warm-cache')->hourly()->withoutOverlapping();
        $schedule->command('leads:push-followup-reminders')->everyThirtyMinutes()->withoutOverlapping();
        $schedule->command('leads:push-new-web-leads')->everyTenMinutes()->withoutOverlapping();
    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');
        require base_path('routes/console.php');
    }
}