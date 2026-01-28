<?php

use App\Jobs\CheckEscrowBalanceJob;
use App\Jobs\CleanupFailedPayrollReservations;
use App\Jobs\DetectStuckPayrollJobs;
use App\Jobs\ReconcileEscrowBalances;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('payments:process-scheduled')->everyMinute();
Schedule::command('payroll:process-scheduled')->everyMinute();
Schedule::command('payments:send-reminders')->everyFifteenMinutes();

// Sync pending jobs with queue every 5 minutes as a backup
// This ensures jobs are always synced even if boot-time sync fails
Schedule::command('jobs:sync')->everyFiveMinutes();

// Daily escrow balance check at midnight
// Notifies business owners if upcoming payments + payroll exceed escrow balance
Schedule::job(new CheckEscrowBalanceJob)
    ->dailyAt('00:00')
    ->withoutOverlapping()
    ->onOneServer();

// Cleanup stale payroll reservations every hour
// Releases escrow funds reserved for failed jobs that have been failed for more than 1 hour
Schedule::job(new CleanupFailedPayrollReservations)
    ->hourly()
    ->withoutOverlapping()
    ->onOneServer();

// Reconcile payroll integrity daily at 2 AM
// Checks for balance mismatches and calculation errors
Schedule::command('payroll:reconcile')
    ->dailyAt('02:00')
    ->withoutOverlapping()
    ->onOneServer();

// Reconcile escrow balances daily at 3 AM
// Detects and fixes balance drift by recalculating from source data
Schedule::job(new ReconcileEscrowBalances)
    ->dailyAt('03:00')
    ->withoutOverlapping()
    ->onOneServer();

// Detect and recover stuck payroll jobs every 30 minutes
// Marks jobs stuck in "processing" status as failed after timeout
Schedule::job(new DetectStuckPayrollJobs)
    ->everyThirtyMinutes()
    ->withoutOverlapping()
    ->onOneServer();
