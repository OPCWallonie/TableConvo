<?php

namespace App\Providers;

use App\Events\RegistrationCancelled;
use App\Listeners\AutoPromoteFromWaitlist;
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
        Event::listen(RegistrationCancelled::class, AutoPromoteFromWaitlist::class);

        RateLimiter::for('register', function (Request $request) {
            return Limit::perMinutes(10, 5)->by($request->ip());
        });

        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        RateLimiter::for('checkout', function (Request $request) {
            return Limit::perMinute(10)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('account-deletion', function (Request $request) {
            return Limit::perMinute(3)->by($request->user()?->id ?: $request->ip());
        });
    }
}
