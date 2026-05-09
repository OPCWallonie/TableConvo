<?php

namespace App\Providers;

use App\Events\RegistrationCancelled;
use App\Listeners\AutoPromoteFromWaitlist;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Cmixin\BusinessDay;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        BusinessDay::enable(
            [Carbon::class, CarbonImmutable::class, \Illuminate\Support\Carbon::class],
            ['region' => 'be-national']
        );

        Event::listen(RegistrationCancelled::class, AutoPromoteFromWaitlist::class);

        RateLimiter::for('register', function (Request $request) {
            return Limit::perMinutes(10, 5)->by($request->ip());
        });
    }
}
