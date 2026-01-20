<?php

use App\Jobs\CheckEscrowBalanceJob;
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
