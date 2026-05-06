<?php

namespace App\Providers;

use App\Services\BusinessDay\BusinessDayService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(BusinessDayService::class);
    }

    public function boot(): void
    {
        //
    }
}
