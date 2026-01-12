<?php

namespace App\Services;

use Carbon\Carbon;

class SouthAfricaHolidayService
{
    /**
     * Get all South Africa public and bank holidays for a given year
     */
    public function getHolidays(int $year): array
    {
        $holidays = [];

        // Fixed date holidays
        $holidays[] = Carbon::create($year, 1, 1)->setTime(0, 0); // New Year's Day
        $holidays[] = Carbon::create($year, 3, 21)->setTime(0, 0); // Human Rights Day
        $holidays[] = Carbon::create($year, 4, 27)->setTime(0, 0); // Freedom Day
        $holidays[] = Carbon::create($year, 5, 1)->setTime(0, 0); // Workers' Day
        $holidays[] = Carbon::create($year, 6, 16)->setTime(0, 0); // Youth Day
        $holidays[] = Carbon::create($year, 8, 9)->setTime(0, 0); // National Women's Day
        $holidays[] = Carbon::create($year, 9, 24)->setTime(0, 0); // Heritage Day
        $holidays[] = Carbon::create($year, 12, 16)->setTime(0, 0); // Day of Reconciliation
        $holidays[] = Carbon::create($year, 12, 25)->setTime(0, 0); // Christmas Day
        $holidays[] = Carbon::create($year, 12, 26)->setTime(0, 0); // Day of Goodwill

        // Calculate Easter-based holidays
        $easter = $this->calculateEaster($year);
        $holidays[] = $easter->copy()->subDays(2)->setTime(0, 0); // Good Friday
        $holidays[] = $easter->copy()->addDay()->setTime(0, 0); // Family Day (Easter Monday)

        return $holidays;
    }

    /**
     * Calculate Easter date for a given year using the Computus algorithm
     */
    private function calculateEaster(int $year): Carbon
    {
        // Computus algorithm
        $a = $year % 19;
        $b = intval($year / 100);
        $c = $year % 100;
        $d = intval($b / 4);
        $e = $b % 4;
        $f = intval(($b + 8) / 25);
        $g = intval(($b - $f + 1) / 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intval($c / 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intval(($a + 11 * $h + 22 * $l) / 451);
        $month = intval(($h + $l - 7 * $m + 114) / 31);
        $day = (($h + $l - 7 * $m + 114) % 31) + 1;

        return Carbon::create($year, $month, $day)->setTime(0, 0);
    }

    /**
     * Check if a date is a weekend
     */
    public function isWeekend(\DateTimeInterface $date): bool
    {
        $dayOfWeek = (int) $date->format('w'); // 0 = Sunday, 6 = Saturday
        return $dayOfWeek === 0 || $dayOfWeek === 6;
    }

    /**
     * Check if a date is a South Africa holiday
     */
    public function isHoliday(\DateTimeInterface $date): bool
    {
        $carbon = Carbon::instance($date);
        $year = $carbon->year;
        $holidays = $this->getHolidays($year);

        foreach ($holidays as $holiday) {
            if ($carbon->isSameDay($holiday)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a date is a valid business day (not weekend and not holiday)
     */
    public function isBusinessDay(\DateTimeInterface $date): bool
    {
        return !$this->isWeekend($date) && !$this->isHoliday($date);
    }

    /**
     * Get the next business day (not weekend, not holiday)
     */
    public function getNextBusinessDay(\DateTimeInterface $date): Carbon
    {
        $carbon = Carbon::instance($date);
        $carbon->addDay();

        while (!$this->isBusinessDay($carbon)) {
            $carbon->addDay();
        }

        return $carbon;
    }

    /**
     * Get the name of a holiday if the date is a holiday
     */
    public function getHolidayName(\DateTimeInterface $date): ?string
    {
        if (!$this->isHoliday($date)) {
            return null;
        }

        $carbon = Carbon::instance($date);
        $year = $carbon->year;
        $day = $carbon->day;
        $month = $carbon->month;

        $holidayNames = [
            "1-1" => "New Year's Day",
            "3-21" => "Human Rights Day",
            "4-27" => "Freedom Day",
            "5-1" => "Workers' Day",
            "6-16" => "Youth Day",
            "8-9" => "National Women's Day",
            "9-24" => "Heritage Day",
            "12-16" => "Day of Reconciliation",
            "12-25" => "Christmas Day",
            "12-26" => "Day of Goodwill",
        ];

        $key = "{$month}-{$day}";
        if (isset($holidayNames[$key])) {
            return $holidayNames[$key];
        }

        // Check Easter-based holidays
        $easter = $this->calculateEaster($year);
        if ($carbon->isSameDay($easter->copy()->subDays(2))) {
            return "Good Friday";
        }
        if ($carbon->isSameDay($easter->copy()->addDay())) {
            return "Family Day";
        }

        return "Public Holiday";
    }
}
