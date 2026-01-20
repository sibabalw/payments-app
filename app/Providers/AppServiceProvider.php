<?php

namespace App\Providers;

use App\Listeners\SendLoginNotification;
use App\Listeners\SyncJobsOnWorkerStart;
use App\Models\Business;
use App\Models\Employee;
use App\Models\PaymentJob;
use App\Models\PaymentSchedule;
use App\Models\PayrollSchedule;
use App\Models\Recipient;
use App\Models\User;
use App\Observers\BusinessObserver;
use App\Observers\EmployeeObserver;
use App\Observers\PaymentJobObserver;
use App\Observers\PaymentScheduleObserver;
use App\Observers\PayrollScheduleObserver;
use App\Observers\RecipientObserver;
use App\Observers\UserObserver;
use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Queue\Events\WorkerStarting;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class AppServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Login::class => [
            SendLoginNotification::class,
        ],
        WorkerStarting::class => [
            SyncJobsOnWorkerStart::class,
        ],
    ];

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
        // Register observers for cascade delete handling (pivot tables have no foreign keys)
        PaymentSchedule::observe(PaymentScheduleObserver::class);
        PayrollSchedule::observe(PayrollScheduleObserver::class);
        Business::observe(BusinessObserver::class);
        Recipient::observe(RecipientObserver::class);
        Employee::observe(EmployeeObserver::class);
        User::observe(UserObserver::class);
        PaymentJob::observe(PaymentJobObserver::class);

        // Validate Redis connection only if enabled
        $this->validateRedisConnection();
    }

    /**
     * Validate Redis connection if Redis features are enabled.
     * This only runs if REDIS_ENABLED is true.
     */
    protected function validateRedisConnection(): void
    {
        if (! config('features.redis.enabled', false)) {
            return;
        }

        try {
            // Try to ping Redis to validate connection
            Redis::connection('default')->ping();

            Log::info('Redis connection validated successfully', [
                'provider' => env('REDIS_PROVIDER', 'self-hosted'),
            ]);
        } catch (\Exception $e) {
            Log::warning('Redis connection validation failed', [
                'error' => $e->getMessage(),
                'provider' => env('REDIS_PROVIDER', 'self-hosted'),
                'note' => 'Application will continue using database backend',
            ]);
        }
    }
}
