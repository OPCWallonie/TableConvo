<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->validateCsrfTokens(except: [
            'webhooks/mollie',
        ]);

        $middleware->web(append: [
            \Spatie\Csp\AddCspHeaders::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('cards:expire')->dailyAt('03:00');
        $schedule->command('attendance:mark-no-shows')->dailyAt('04:00');
        $schedule->command('cards:warn-expiration')->dailyAt('09:00');
        $schedule->command('sessions:send-reminders')->hourly();
    })
    ->create();
