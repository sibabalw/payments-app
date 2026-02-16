<?php

use App\Services\CronExpressionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('calculates next_run as February 28 when monthly day-29 schedule is executed after January has passed', function () {
    // Scenario: schedule set for 29th of each month at 08:00 (cron: 0 8 29 * *)
    // We "manually execute" after January has passed (e.g. we run the job on Feb 1).
    // Observed: calculated next_run must be Feb 28 in non-leap year (Feb has 28 days), not Feb 29.
    $cronService = app(CronExpressionService::class);
    $frequency = '0 8 29 * *';

    // Freeze time to February 1, 2025 10:00 — January has passed
    Carbon::setTestNow(Carbon::parse('2025-02-01 10:00:00'));

    try {
        $nextRun = $cronService->getNextRunDate($frequency, now());

        // Observed: next_run is 2025-02-28 08:00 (last day of Feb in non-leap year)
        expect($nextRun->format('Y-m-d'))->toBe('2025-02-28')
            ->and($nextRun->format('H:i'))->toBe('08:00');
    } finally {
        Carbon::setTestNow();
    }
});

it('calculates next_run as February 29 when monthly day-29 in leap year', function () {
    // Same scenario but leap year 2024: after executing past Jan 29, next run is Feb 29
    $cronService = app(CronExpressionService::class);
    $frequency = '0 8 29 * *';

    Carbon::setTestNow(Carbon::parse('2024-02-01 10:00:00'));

    try {
        $nextRun = $cronService->getNextRunDate($frequency, now());

        expect($nextRun->format('Y-m-d'))->toBe('2024-02-29')
            ->and($nextRun->format('H:i'))->toBe('08:00');
    } finally {
        Carbon::setTestNow();
    }
});
