<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\LeaveEntry;
use App\Models\TimeEntry;
use Carbon\Carbon;

class SalaryCalculationService
{
    protected OvertimeCalculationService $overtimeService;

    protected SouthAfricaHolidayService $holidayService;

    public function __construct(
        OvertimeCalculationService $overtimeService,
        SouthAfricaHolidayService $holidayService
    ) {
        $this->overtimeService = $overtimeService;
        $this->holidayService = $holidayService;
    }

    /**
     * Calculate monthly salary from time entries
     *
     * @return array ['gross_salary' => float, 'breakdown' => array]
     */
    public function calculateMonthlySalary(Employee $employee, Carbon $startDate, Carbon $endDate): array
    {
        if (! $employee->hourly_rate) {
            // Employee uses fixed salary, return it
            return [
                'gross_salary' => (float) $employee->gross_salary,
                'breakdown' => [
                    'type' => 'fixed',
                    'base_salary' => (float) $employee->gross_salary,
                ],
            ];
        }

        $timeEntries = $this->getTimeEntriesForPeriod($employee, $startDate, $endDate);
        $leaveEntries = $this->getLeaveEntriesForPeriod($employee, $startDate, $endDate);

        $totalEarnings = 0;
        $breakdown = [
            'type' => 'hourly',
            'regular_hours' => 0,
            'regular_earnings' => 0,
            'overtime_hours' => 0,
            'overtime_earnings' => 0,
            'weekend_hours' => 0,
            'weekend_earnings' => 0,
            'holiday_hours' => 0,
            'holiday_earnings' => 0,
            'bonus_amount' => 0,
            'paid_leave_hours' => 0,
            'paid_leave_earnings' => 0,
        ];

        // Calculate from time entries
        foreach ($timeEntries as $entry) {
            $entryEarnings = $entry->getTotalEarnings();
            $totalEarnings += $entryEarnings;

            $breakdown['regular_hours'] += (float) $entry->regular_hours;
            $breakdown['overtime_hours'] += (float) $entry->overtime_hours;
            $breakdown['weekend_hours'] += (float) $entry->weekend_hours;
            $breakdown['holiday_hours'] += (float) $entry->holiday_hours;
            $breakdown['bonus_amount'] += (float) $entry->bonus_amount;
        }

        // Calculate earnings breakdown
        $hourlyRate = (float) $employee->hourly_rate;
        $breakdown['regular_earnings'] = round($breakdown['regular_hours'] * $hourlyRate, 2);

        $overtimeRate = $hourlyRate * (float) ($employee->overtime_rate_multiplier ?? 1.5);
        $breakdown['overtime_earnings'] = round($breakdown['overtime_hours'] * $overtimeRate, 2);

        $weekendRate = $hourlyRate * (float) ($employee->weekend_rate_multiplier ?? 1.5);
        $breakdown['weekend_earnings'] = round($breakdown['weekend_hours'] * $weekendRate, 2);

        $holidayRate = $hourlyRate * (float) ($employee->holiday_rate_multiplier ?? 2.0);
        $breakdown['holiday_earnings'] = round($breakdown['holiday_hours'] * $holidayRate, 2);

        // Apply paid leave
        $paidLeaveHours = 0;
        foreach ($leaveEntries as $leave) {
            if ($leave->leave_type === 'paid') {
                $paidLeaveHours += (float) $leave->hours;
            }
        }

        $breakdown['paid_leave_hours'] = $paidLeaveHours;
        $breakdown['paid_leave_earnings'] = round($paidLeaveHours * $hourlyRate, 2);
        $totalEarnings += $breakdown['paid_leave_earnings'];

        return [
            'gross_salary' => round($totalEarnings, 2),
            'breakdown' => $breakdown,
        ];
    }

    /**
     * Get time entries for a period
     */
    public function getTimeEntriesForPeriod(Employee $employee, Carbon $startDate, Carbon $endDate): \Illuminate\Database\Eloquent\Collection
    {
        return TimeEntry::where('employee_id', $employee->id)
            ->whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->whereNotNull('sign_out_time') // Only completed entries
            ->get();
    }

    /**
     * Get leave entries for a period
     */
    public function getLeaveEntriesForPeriod(Employee $employee, Carbon $startDate, Carbon $endDate): \Illuminate\Database\Eloquent\Collection
    {
        return LeaveEntry::where('employee_id', $employee->id)
            ->where(function ($query) use ($startDate, $endDate) {
                $query->whereBetween('start_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
                    ->orWhereBetween('end_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
                    ->orWhere(function ($q) use ($startDate, $endDate) {
                        $q->where('start_date', '<=', $startDate->format('Y-m-d'))
                            ->where('end_date', '>=', $endDate->format('Y-m-d'));
                    });
            })
            ->get();
    }

    /**
     * Calculate total earnings for a period
     */
    public function calculateTotalEarnings(Employee $employee, Carbon $startDate, Carbon $endDate): float
    {
        $result = $this->calculateMonthlySalary($employee, $startDate, $endDate);

        return $result['gross_salary'];
    }
}
