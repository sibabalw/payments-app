<?php

namespace App\Services;

use Carbon\Carbon;
use Cron\CronExpression;
use DateTimeInterface;

class CronExpressionService
{
    public function __construct(
        protected SouthAfricaHolidayService $holidayService
    ) {}

    /**
     * Generate cron expression for one-time execution
     */
    public function fromOneTime(DateTimeInterface $dateTime): string
    {
        $carbon = Carbon::instance($dateTime);

        // Format: minute hour day month *
        return sprintf(
            '%d %d %d %d *',
            $carbon->minute,
            $carbon->hour,
            $carbon->day,
            $carbon->month
        );
    }

    /**
     * Generate cron expression for recurring execution
     */
    public function fromRecurring(DateTimeInterface $dateTime, string $frequency): string
    {
        $carbon = Carbon::instance($dateTime);
        $minute = $carbon->minute;
        $hour = $carbon->hour;

        return match ($frequency) {
            'daily' => sprintf('%d %d * * *', $minute, $hour),
            'weekly' => sprintf('%d %d * * %d', $minute, $hour, $carbon->dayOfWeek),
            'monthly' => sprintf('%d %d %d * *', $minute, $hour, $carbon->day),
            default => throw new \InvalidArgumentException("Invalid frequency: {$frequency}"),
        };
    }

    /**
     * Get the next run date for a cron expression, with month-aware handling for monthly schedules.
     * For monthly schedules (e.g. day 30), if the run time this month is still in the future, returns
     * it; otherwise returns next month. If the target month has fewer days (e.g. February),
     * returns the last day of that month instead of skipping to the next month.
     */
    public function getNextRunDate(string $cronExpression, ?DateTimeInterface $fromDate = null): Carbon
    {
        $from = $fromDate !== null
            ? Carbon::parse($fromDate)->setTimezone(config('app.timezone'))
            : now(config('app.timezone'));

        $cronExpression = trim(preg_replace('/\s+/', ' ', $cronExpression));
        $parts = array_values(array_filter(explode(' ', $cronExpression), fn (string $p): bool => $p !== ''));
        $isMonthly = count($parts) === 5
            && is_numeric($parts[2])
            && (int) $parts[2] >= 1
            && (int) $parts[2] <= 31
            && trim($parts[3] ?? '') === '*'
            && trim($parts[4] ?? '') === '*';
        if ($isMonthly) {
            $dayOfMonth = (int) $parts[2];
            $hour = (int) $parts[1];
            $minute = (int) $parts[0];

            $thisMonth = $from->copy()->startOfMonth();
            $lastDayThisMonth = (int) $thisMonth->copy()->endOfMonth()->format('d');
            $dayThisMonth = min($dayOfMonth, $lastDayThisMonth);
            $candidateThisMonth = $thisMonth->copy()->day($dayThisMonth)->setTime($hour, $minute);

            if ($candidateThisMonth->greaterThan($from)) {
                return $candidateThisMonth;
            }

            $nextMonth = $from->copy()->startOfMonth()->addMonth();
            $lastDay = (int) $nextMonth->copy()->endOfMonth()->format('d');
            $day = min($dayOfMonth, $lastDay);

            return $nextMonth->copy()->day($day)->setTime($hour, $minute);
        }

        $cron = CronExpression::factory($cronExpression);

        return Carbon::instance($cron->getNextRunDate($from));
    }

    /**
     * Validate that a date/time is a business day
     */
    public function validateBusinessDay(DateTimeInterface $dateTime): bool
    {
        return $this->holidayService->isBusinessDay($dateTime);
    }

    /**
     * Adjust a date/time to the next business day if needed
     */
    public function adjustToBusinessDay(DateTimeInterface $dateTime): Carbon
    {
        $carbon = Carbon::instance($dateTime);

        if ($this->holidayService->isBusinessDay($carbon)) {
            return $carbon;
        }

        return $this->holidayService->getNextBusinessDay($carbon);
    }
}
