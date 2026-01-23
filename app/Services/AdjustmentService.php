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
     *
     * @param  Employee  $employee  The employee to get adjustments for
     * @param  Carbon  $periodStart  Start date of the payroll period
     * @param  Carbon  $periodEnd  End date of the payroll period
     * @param  int|null  $payrollScheduleId  The payroll schedule ID (required for once-off adjustments)
     * @return Collection Collection of Adjustment models
     */
    public function getValidAdjustments(Employee $employee, Carbon $periodStart, Carbon $periodEnd, ?int $payrollScheduleId = null): Collection
    {
        // Get company-wide adjustments (employee_id is null)
        $companyAdjustments = Adjustment::where('business_id', $employee->business_id)
            ->whereNull('employee_id')
            ->where('is_active', true)
            ->where(function ($query) use ($periodStart, $periodEnd, $payrollScheduleId) {
                // Recurring adjustments (apply to all schedules)
                $query->where(function ($q) {
                    $q->where('is_recurring', true)
                        ->whereNull('payroll_schedule_id');
                })
                // Or once-off adjustments
                    ->orWhere(function ($subQuery) use ($periodStart, $periodEnd, $payrollScheduleId) {
                        $subQuery->where('is_recurring', false)
                            ->where('payroll_period_start', '<=', $periodEnd)
                            ->where('payroll_period_end', '>=', $periodStart);

                        // If payroll_schedule_id is provided, only match that schedule
                        // If null (for preview), show all once-off adjustments matching the period
                        if ($payrollScheduleId !== null) {
                            $subQuery->where('payroll_schedule_id', $payrollScheduleId);
                        }
                    });
            })
            ->get();

        // Get employee-specific adjustments
        // For employee-specific once-off adjustments, we use EXACT period matching
        // This prevents cross-schedule contamination when multiple schedules run in the same month
        $employeeAdjustments = Adjustment::where('business_id', $employee->business_id)
            ->where('employee_id', $employee->id)
            ->where('is_active', true)
            ->where(function ($query) use ($periodStart, $periodEnd, $payrollScheduleId) {
                // Recurring adjustments (apply to all schedules)
                $query->where(function ($q) {
                    $q->where('is_recurring', true)
                        ->whereNull('payroll_schedule_id');
                })
                // Or once-off adjustments - EXACT period match required for employee-specific
                // This ensures that an adjustment created for Schedule A won't be picked up by Schedule B
                // even if both schedules run in the same month for the same employee
                    ->orWhere(function ($subQuery) use ($periodStart, $periodEnd, $payrollScheduleId) {
                        $subQuery->where('is_recurring', false)
                            // EXACT period match (not overlap) for employee-specific once-off
                            ->where('payroll_period_start', '=', $periodStart->format('Y-m-d'))
                            ->where('payroll_period_end', '=', $periodEnd->format('Y-m-d'));

                        // Schedule ID is REQUIRED and must match exactly
                        // This is the key: each schedule only gets adjustments tied to it
                        if ($payrollScheduleId !== null) {
                            $subQuery->where('payroll_schedule_id', $payrollScheduleId);
                        }
                    });
            })
            ->get();

        // Merge and return
        return $companyAdjustments->merge($employeeAdjustments);
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
                'is_recurring' => $adjustment->is_recurring,
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

        return [
            'final_net_salary' => round($finalNetSalary, 2),
            'adjustments_breakdown' => $adjustmentsBreakdown,
            'total_adjustments' => round($totalAdditions - $totalDeductions, 2),
            'total_deductions' => round($totalDeductions, 2),
            'total_additions' => round($totalAdditions, 2),
        ];
    }
}
