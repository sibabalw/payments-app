<?php

namespace App\Providers;

use App\Events\AdminLoginCompleted;
use App\Listeners\SendLoginNotification;
use App\Listeners\SyncJobsOnWorkerStart;
use App\Models\Business;
use App\Models\Employee;
use App\Models\PaymentJob;
use App\Models\PaymentSchedule;
use App\Models\PayrollJob;
use App\Models\PayrollSchedule;
use App\Models\Recipient;
use App\Models\Ticket;
use App\Models\User;
use App\Observers\BusinessObserver;
use App\Observers\EmployeeObserver;
use App\Observers\PaymentJobObserver;
use App\Observers\PaymentScheduleObserver;
use App\Observers\PayrollJobObserver;
use App\Observers\PayrollScheduleObserver;
use App\Observers\RecipientObserver;
use App\Observers\TicketObserver;
use App\Observers\UserObserver;
use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Queue\Events\WorkerStarting;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\URL;

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
        AdminLoginCompleted::class => [
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
        // Force HTTPS in production so signed URLs (e.g. email verification) use correct scheme
        if (config('app.env') === 'production') {
            URL::forceScheme('https');
        }

        $this->ensureWayfinderDirectoriesExist();

        // Register observers for cascade delete handling (pivot tables have no foreign keys)
        PaymentSchedule::observe(PaymentScheduleObserver::class);
        PayrollSchedule::observe(PayrollScheduleObserver::class);
        Business::observe(BusinessObserver::class);
        Recipient::observe(RecipientObserver::class);
        Employee::observe(EmployeeObserver::class);
        User::observe(UserObserver::class);
        PaymentJob::observe(PaymentJobObserver::class);
        PayrollJob::observe(PayrollJobObserver::class);
        Ticket::observe(TicketObserver::class);

        // Validate Redis connection only if enabled
        $this->validateRedisConnection();
    }

    /**
     * Ensure Wayfinder-generated directories exist and are directories (not files).
     * Prevents "mkdir(): File exists" when running wayfinder:generate.
     */
    protected function ensureWayfinderDirectoriesExist(): void
    {
        $base = resource_path('js');

        foreach (['wayfinder', 'actions', 'routes'] as $dir) {
            $path = $base.DIRECTORY_SEPARATOR.$dir;

            if (file_exists($path) && ! is_dir($path)) {
                File::delete($path);
            }

            if (! file_exists($path)) {
                File::makeDirectory($path, 0755, true);
            }
        }
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
