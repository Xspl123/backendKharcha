<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Console\Scheduling\Schedule;   // 👈 ये नई line add करो

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )

    // 👇 ये पूरा block add करो
    ->withSchedule(function (Schedule $schedule) {
        $schedule->command('app:send-reminders')->dailyAt('09:00');

        $schedule->command('dashboard:warm-cache')
            ->hourly()
            ->withoutOverlapping();

        $schedule->command('leads:push-followup-reminders')
            ->everyThirtyMinutes()
            ->withoutOverlapping();
    })

    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'permission'   => \App\Http\Middleware\CheckPermission::class,
            'super_admin'  => \App\Http\Middleware\EnsureSuperAdmin::class,
            'org.set'      => \App\Http\Middleware\SetOrganisation::class,
            'org.access'   => \App\Http\Middleware\EnsureOrgAccess::class,
            'tenant.org'   => \App\Http\Middleware\InitializeTenancyFromOrganisation::class,
            'tenant.slug'  => \App\Http\Middleware\InitializeTenancyFromSlug::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();