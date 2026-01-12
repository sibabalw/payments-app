<?php

namespace App\Providers;

use App\Models\PaymentJob;
use App\Models\PaymentSchedule;
use App\Observers\PaymentJobObserver;
use App\Observers\PaymentScheduleObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        PaymentSchedule::observe(PaymentScheduleObserver::class);
        PaymentJob::observe(PaymentJobObserver::class);
    }
}
