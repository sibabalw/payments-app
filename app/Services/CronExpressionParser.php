<?php

namespace App\Services;

use Carbon\Carbon;

class CronExpressionParser
{
    public function __construct(
        protected CronExpressionService $cronService
    ) {}

    /**
     * Parse a cron expression to extract date/time information
     * Returns array with 'date', 'time', and 'frequency' (if recurring)
     */
    public function parse(string $cronExpression): ?array
    {
        try {
            $parts = explode(' ', $cronExpression);

            if (count($parts) !== 5) {
                return null;
            }

            [$minute, $hour, $day, $month, $weekday] = $parts;

            // Determine if it's one-time or recurring
            $isOneTime = $weekday === '*' && $day !== '*' && $month !== '*';
            $isDaily = $day === '*' && $month === '*' && $weekday === '*';
            $isWeekly = $day === '*' && $month === '*' && $weekday !== '*';
            $isMonthly = $day !== '*' && $month === '*' && $weekday === '*';

            // Get next run date to extract the actual date/time (month-aware for monthly schedules)
            $carbon = $this->cronService->getNextRunDate($cronExpression);

            $result = [
                'date' => $carbon->format('Y-m-d'),
                'time' => $carbon->format('H:i'),
                'minute' => (int) $minute,
                'hour' => (int) $hour,
            ];

            if ($isOneTime) {
                $result['frequency'] = null;
                $result['schedule_type'] = 'one_time';
            } elseif ($isDaily) {
                $result['frequency'] = 'daily';
                $result['schedule_type'] = 'recurring';
            } elseif ($isWeekly) {
                $result['frequency'] = 'weekly';
                $result['schedule_type'] = 'recurring';
            } elseif ($isMonthly) {
                $result['frequency'] = 'monthly';
                $result['schedule_type'] = 'recurring';
            } else {
                // Complex cron expression - can't fully parse
                $result['frequency'] = null;
                $result['schedule_type'] = 'recurring';
            }

            return $result;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Extract just the date and time from a cron expression
     */
    public function extractDateTime(string $cronExpression): ?Carbon
    {
        try {
            return $this->cronService->getNextRunDate($cronExpression);
        } catch (\Exception $e) {
            return null;
        }
    }
}
