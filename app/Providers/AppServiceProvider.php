<?php

namespace App\Providers;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Cmixin\BusinessDay;
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
    }
}
