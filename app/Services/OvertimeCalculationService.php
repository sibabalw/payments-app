<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeSchedule;
use Carbon\Carbon;

class OvertimeCalculationService
{
    /**
     * Calculate overtime hours for an employee on a specific date
     * Overtime = hours worked that exceed scheduled hours
     *
     * @return float Overtime hours
     */
    public function calculateOvertime(Employee $employee, Carbon $date, float $totalHoursWorked): float
    {
        $scheduledHours = EmployeeSchedule::getScheduledHoursForDate($employee, $date);

        // If no schedule set, no overtime (all hours are regular)
        if ($scheduledHours <= 0) {
            return 0;
        }

        // Overtime = hours worked - scheduled hours (if positive)
        $overtime = max(0, $totalHoursWorked - $scheduledHours);

        return round($overtime, 2);
    }

    /**
     * Check if hours worked exceed scheduled hours (overtime)
     */
    public function isOvertime(Employee $employee, Carbon $date, float $hoursWorked): bool
    {
        $scheduledHours = EmployeeSchedule::getScheduledHoursForDate($employee, $date);

        return $scheduledHours > 0 && $hoursWorked > $scheduledHours;
    }

    /**
     * Get scheduled hours for a date
     */
    public function getScheduledHours(Employee $employee, Carbon $date): float
    {
        return EmployeeSchedule::getScheduledHoursForDate($employee, $date);
    }
}
