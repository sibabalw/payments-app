<?php

namespace App\Services;

use App\Models\Adjustment;
use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

class AdjustmentService
{
    /**
     * Get all adjustments valid for a payroll period
     * Unified query logic: single query with consistent overlap matching for all adjustments
     *
     * @param  Employee  $employee  The employee to get adjustments for
     * @param  Carbon  $periodStart  Start date of the payroll period
     * @param  Carbon  $periodEnd  End date of the payroll period
     * @param  int|null  $payrollScheduleId  Optional schedule ID for filtering (not stored, used for auto-detection)
     * @return Collection Collection of Adjustment models
     */
    public function getValidAdjustments(Employee $employee, Carbon $periodStart, Carbon $periodEnd, ?int $payrollScheduleId = null): Collection
    {
        $query = Adjustment::where('business_id', $employee->business_id)
            ->where('is_active', true)
            ->where(function ($q) use ($employee) {
                // Company-wide OR employee-specific
                $q->whereNull('employee_id')
                    ->orWhere('employee_id', $employee->id);
            })
            ->where(function ($q) use ($periodStart, $periodEnd) {
                // Forever (null period = recurring) OR overlaps with payroll period
                $q->whereNull('period_start')  // Recurring/forever adjustments
                    ->whereNull('period_end')
                    ->orWhere(function ($subQ) use ($periodStart, $periodEnd) {
                        // Once-off adjustments that overlap with the period
                        $subQ->whereNotNull('period_start')
                            ->whereNotNull('period_end')
                            ->where('period_start', '<=', $periodEnd)
                            ->where('period_end', '>=', $periodStart);
                    });
            });

        // When a schedule is specified, only include adjustments for that schedule or company-wide (null)
        if ($payrollScheduleId !== null) {
            $query->where(function ($q) use ($payrollScheduleId) {
                $q->whereNull('payroll_schedule_id')
                    ->orWhere('payroll_schedule_id', $payrollScheduleId);
            });
        }

        return $query->get();
    }

    /**
     * Apply adjustments to net salary
     *
     * @param  float  $netSalary  Net salary after statutory deductions
     * @param  Collection  $adjustments  Collection of Adjustment models
     * @param  float  $grossSalary  Gross salary (for percentage calculations)
     * @return array Returns array with final_net_salary, adjustments_breakdown, and total_adjustments
     */
    public function applyAdjustments(float $netSalary, Collection $adjustments, float $grossSalary): array
    {
        $adjustmentsBreakdown = [];
        $totalDeductions = 0;
        $totalAdditions = 0;

        foreach ($adjustments as $adjustment) {
            $amount = $adjustment->calculateAmount($grossSalary);

            if ($amount <= 0) {
                continue;
            }

            $adjustmentData = [
                'id' => $adjustment->id,
                'name' => $adjustment->name,
                'type' => $adjustment->type,
                'adjustment_type' => $adjustment->adjustment_type,
                'amount' => $amount,
                'original_amount' => $adjustment->amount,
                'is_recurring' => $adjustment->isRecurring(),
            ];

            if ($adjustment->adjustment_type === 'deduction') {
                $totalDeductions += $amount;
            } else {
                $totalAdditions += $amount;
            }

            $adjustmentsBreakdown[] = $adjustmentData;
        }

        // Apply adjustments: net_after_statutory + total_additions - total_deductions
        $finalNetSalary = $netSalary + $totalAdditions - $totalDeductions;

        // Negative net salary protection: Set to 0 if deductions exceed net salary
        $hasNegativeNet = $finalNetSalary < 0;
        if ($hasNegativeNet) {
            \Illuminate\Support\Facades\Log::warning('Deductions exceed net salary', [
                'net_after_statutory' => $netSalary,
                'total_additions' => $totalAdditions,
                'total_deductions' => $totalDeductions,
                'calculated_net' => $finalNetSalary,
            ]);
            $finalNetSalary = 0;
        }

        return [
            'final_net_salary' => round($finalNetSalary, 2),
            'adjustments_breakdown' => $adjustmentsBreakdown,
            'total_adjustments' => round($totalAdditions - $totalDeductions, 2),
            'total_deductions' => round($totalDeductions, 2),
            'total_additions' => round($totalAdditions, 2),
            'has_negative_net' => $hasNegativeNet,
            'negative_net_warning' => $hasNegativeNet ? 'Deductions exceed net salary. Net salary set to 0.' : null,
        ];
    }

    /**
     * Detect scope from context
     * Returns 'company' or 'employee' based on employee_id
     *
     * @param  int|null  $employeeId  Employee ID or null
     * @return string 'company' or 'employee'
     */
    public function detectScope(?int $employeeId): string
    {
        return $employeeId === null ? 'company' : 'employee';
    }

    /**
     * Detect recurrence from period
     * Returns true if recurring (period is null), false if one-off (period is set)
     *
     * @param  Carbon|null  $periodStart  Period start date
     * @param  Carbon|null  $periodEnd  Period end date
     * @return bool True if recurring, false if one-off
     */
    public function detectRecurrence(?Carbon $periodStart, ?Carbon $periodEnd): bool
    {
        return $periodStart === null && $periodEnd === null;
    }

    /**
     * Auto-detect schedules for an employee in a given period
     * Returns collection of schedule IDs that process the employee for that period
     *
     * @param  Employee  $employee  The employee
     * @param  Carbon  $periodStart  Period start date
     * @param  Carbon  $periodEnd  Period end date
     * @return \Illuminate\Support\Collection Collection of schedule IDs
     */
    public function autoDetectSchedules(Employee $employee, Carbon $periodStart, Carbon $periodEnd): \Illuminate\Support\Collection
    {
        return $employee->payrollSchedules()
            ->where('status', 'active')
            ->get()
            ->filter(function ($schedule) use ($periodStart, $periodEnd) {
                $schedulePeriod = $schedule->calculatePayPeriod();
                $schedulePeriodStart = $schedulePeriod['start'];
                $schedulePeriodEnd = $schedulePeriod['end'];

                // Check if schedule's period overlaps with the given period
                return $schedulePeriodStart->lte($periodEnd) && $schedulePeriodEnd->gte($periodStart);
            })
            ->pluck('id');
    }
}
