<?php

use App\Services\CronExpressionService;
use Carbon\Carbon;

beforeEach(function () {
    $this->cronService = app(CronExpressionService::class);
});

it('returns same day when monthly day 30 and run time is still in the future on January 30', function () {
    $from = Carbon::parse('2026-01-30 07:50:00');
    $cron = '55 9 30 * *';

    $nextRun = $this->cronService->getNextRunDate($cron, $from);

    expect($nextRun->format('Y-m-d'))->toBe('2026-01-30')
        ->and($nextRun->format('H:i'))->toBe('09:55');
});

it('returns February 28 as next run when monthly day 30 from January 30 at same time in non-leap year', function () {
    $from = Carbon::parse('2025-01-30 08:00:00');
    $cron = '0 8 30 * *';

    $nextRun = $this->cronService->getNextRunDate($cron, $from);

    expect($nextRun->format('Y-m-d'))->toBe('2025-02-28')
        ->and($nextRun->format('H:i'))->toBe('08:00');
});

it('returns February 29 as next run when monthly day 30 from January 30 in leap year', function () {
    $from = Carbon::parse('2024-01-30 08:00:00');
    $cron = '0 8 30 * *';

    $nextRun = $this->cronService->getNextRunDate($cron, $from);

    expect($nextRun->format('Y-m-d'))->toBe('2024-02-29')
        ->and($nextRun->format('H:i'))->toBe('08:00');
});

it('returns February 28 or 29 as next run when monthly day 31 from January 31', function () {
    $from = Carbon::parse('2025-01-31 09:00:00');
    $cron = '0 9 31 * *';

    $nextRun = $this->cronService->getNextRunDate($cron, $from);

    expect($nextRun->format('Y-m-d'))->toBe('2025-02-28')
        ->and($nextRun->format('H:i'))->toBe('09:00');

    $fromLeap = Carbon::parse('2024-01-31 09:00:00');
    $nextRunLeap = $this->cronService->getNextRunDate($cron, $fromLeap);
    expect($nextRunLeap->format('Y-m-d'))->toBe('2024-02-29');
});

it('returns February 28 when monthly day 29 from January 29 in non-leap year', function () {
    $from = Carbon::parse('2025-01-29 10:00:00');
    $cron = '0 10 29 * *';

    $nextRun = $this->cronService->getNextRunDate($cron, $from);

    expect($nextRun->format('Y-m-d'))->toBe('2025-02-28');
});

it('returns February 29 when monthly day 29 from January 29 in leap year', function () {
    $from = Carbon::parse('2024-01-29 10:00:00');
    $cron = '0 10 29 * *';

    $nextRun = $this->cronService->getNextRunDate($cron, $from);

    expect($nextRun->format('Y-m-d'))->toBe('2024-02-29');
});

it('returns March 28 as next run when monthly day 28 from February 28 in non-leap year', function () {
    $from = Carbon::parse('2025-02-28 08:00:00');
    $cron = '0 8 28 * *';

    $nextRun = $this->cronService->getNextRunDate($cron, $from);

    expect($nextRun->format('Y-m-d'))->toBe('2025-03-28');
});

it('returns March 30 as next run when monthly day 30 from February 28 in non-leap year', function () {
    $from = Carbon::parse('2025-02-28 08:00:00');
    $cron = '0 8 30 * *';

    $nextRun = $this->cronService->getNextRunDate($cron, $from);

    expect($nextRun->format('Y-m-d'))->toBe('2025-03-30')
        ->and($nextRun->format('H:i'))->toBe('08:00');
});

it('returns March 29 as next run when monthly day 29 from February 29 in leap year', function () {
    $from = Carbon::parse('2024-02-29 08:00:00');
    $cron = '0 8 29 * *';

    $nextRun = $this->cronService->getNextRunDate($cron, $from);

    expect($nextRun->format('Y-m-d'))->toBe('2024-03-29');
});

it('uses standard cron for daily schedule and does not skip February', function () {
    $from = Carbon::parse('2025-01-30 08:00:00');
    $cron = '0 8 * * *';

    $nextRun = $this->cronService->getNextRunDate($cron, $from);

    expect($nextRun->format('Y-m-d'))->toBe('2025-01-31');
});

it('uses standard cron for weekly schedule', function () {
    $from = Carbon::parse('2025-01-30 08:00:00');
    $cron = '0 8 * * 4';

    $nextRun = $this->cronService->getNextRunDate($cron, $from);

    expect($nextRun->dayOfWeek)->toBe(4)
        ->and($nextRun->format('H:i'))->toBe('08:00');
});

it('caps day 31 to 30 in April for monthly day 31', function () {
    $from = Carbon::parse('2025-03-31 08:00:00');
    $cron = '0 8 31 * *';

    $nextRun = $this->cronService->getNextRunDate($cron, $from);

    expect($nextRun->format('Y-m-d'))->toBe('2025-04-30');
});
