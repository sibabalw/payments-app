<?php

namespace App\Services;

use Carbon\Carbon;
use DateTimeInterface;

class CronExpressionService
{
    public function __construct(
        protected SouthAfricaHolidayService $holidayService
    ) {
    }

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
