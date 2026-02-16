<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\PayrollJob;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;

class PayrollCalculationService
{
    public function __construct(
        protected SouthAfricanTaxService $taxService,
        protected AdjustmentService $adjustmentService,
        protected SalaryCalculationService $salaryCalculationService,
        protected AuditService $auditService
    ) {}

    /**
     * Calculate payroll for an employee for a given period
     * Returns immutable calculation object with all inputs/outputs and hash
     *
     * @return array Calculation result with hash and snapshot
     */
    public function calculatePayroll(Employee $employee, Carbon $periodStart, Carbon $periodEnd): array
    {
        // Calculate gross salary
        if ($employee->hourly_rate) {
            $salaryResult = $this->salaryCalculationService->calculateMonthlySalary(
                $employee,
                $periodStart,
                $periodEnd
            );
            $grossSalary = $salaryResult['gross_salary'];
        } else {
            $grossSalary = $employee->gross_salary;
        }

        // Check UIF exemption
        $uifExempt = $employee->isUIFExempt();

        // Calculate statutory deductions
        $breakdown = $this->taxService->calculateNetSalary($grossSalary, [
            'uif_exempt' => $uifExempt,
        ]);

        // Get valid adjustments
        $adjustments = $this->adjustmentService->getValidAdjustments(
            $employee,
            $periodStart,
            $periodEnd,
            null // schedule_id not needed for calculation
        );

        // Apply adjustments
        $adjustmentResult = $this->adjustmentService->applyAdjustments(
            $breakdown['net'],
            $adjustments,
            $grossSalary
        );

        // Create complete employee snapshot with all fields relevant to calculation
        $employeeSnapshot = [
            'id' => $employee->id,
            'name' => $employee->name,
            'email' => $employee->email,
            'id_number' => $employee->id_number,
            'tax_number' => $employee->tax_number,
            'employment_type' => $employee->employment_type,
            'gross_salary' => $employee->gross_salary,
            'hourly_rate' => $employee->hourly_rate,
            'hours_worked_per_month' => $employee->hours_worked_per_month,
            'overtime_rate_multiplier' => $employee->overtime_rate_multiplier,
            'weekend_rate_multiplier' => $employee->weekend_rate_multiplier,
            'holiday_rate_multiplier' => $employee->holiday_rate_multiplier,
            'tax_status' => $employee->tax_status,
            'business_id' => $employee->business_id,
            'uif_exempt' => $uifExempt, // Include calculated UIF exemption status
            'snapshot_taken_at' => now()->toIso8601String(),
        ];

        // Create calculation snapshot
        $calculationSnapshot = [
            'gross_salary' => $grossSalary,
            'paye_amount' => $breakdown['paye'],
            'uif_amount' => $breakdown['uif'],
            'sdl_amount' => $breakdown['sdl'],
            'uif_exempt' => $uifExempt,
            'net_after_statutory' => $breakdown['net'],
            'adjustments' => $adjustmentResult['adjustments_breakdown'] ?? [],
            'total_deductions' => $adjustmentResult['total_deductions'] ?? 0,
            'total_additions' => $adjustmentResult['total_additions'] ?? 0,
            'final_net_salary' => $adjustmentResult['final_net_salary'],
            'period_start' => $periodStart->format('Y-m-d'),
            'period_end' => $periodEnd->format('Y-m-d'),
            'calculated_at' => now()->toIso8601String(),
        ];

        // Prepare adjustment data for hash (include full details, not just IDs)
        $adjustmentHashData = $adjustments->map(function ($adjustment) use ($grossSalary) {
            return [
                'id' => $adjustment->id,
                'type' => $adjustment->type, // 'percentage' or 'fixed'
                'amount' => (float) $adjustment->amount, // Base amount or percentage
                'adjustment_type' => $adjustment->adjustment_type, // 'deduction' or 'addition'
                'calculated_amount' => $adjustment->calculateAmount($grossSalary), // Actual calculated amount
                'period_start' => $adjustment->period_start?->format('Y-m-d'),
                'period_end' => $adjustment->period_end?->format('Y-m-d'),
                'is_recurring' => $adjustment->isRecurring(),
            ];
        })->sortBy('id')->values()->toArray();

        // Generate calculation hash from inputs (including full adjustment data)
        $calculationHash = $this->generateCalculationHash([
            'employee_id' => $employee->id,
            'gross_salary' => $grossSalary,
            'uif_exempt' => $uifExempt,
            'period_start' => $periodStart->format('Y-m-d'),
            'period_end' => $periodEnd->format('Y-m-d'),
            'adjustments' => $adjustmentHashData,
            'tax_year' => $periodStart->year,
            'calculation_version' => $this->getCalculationVersion(),
        ]);

        $result = [
            'gross_salary' => $grossSalary,
            'paye_amount' => $breakdown['paye'],
            'uif_amount' => $breakdown['uif'],
            'sdl_amount' => $breakdown['sdl'],
            'adjustments' => $adjustmentResult['adjustments_breakdown'] ?? [],
            'net_salary' => $adjustmentResult['final_net_salary'],
            'calculation_hash' => $calculationHash,
            'calculation_version' => $this->getCalculationVersion(),
            'adjustment_inputs' => $adjustmentHashData,
            'calculation_snapshot' => $calculationSnapshot,
            'employee_snapshot' => $employeeSnapshot,
            'has_negative_net' => $adjustmentResult['has_negative_net'] ?? false,
        ];

        // Audit log the calculation
        $this->auditService->log(
            'payroll.calculated',
            $employee,
            [
                'employee_id' => $employee->id,
                'period_start' => $periodStart->format('Y-m-d'),
                'period_end' => $periodEnd->format('Y-m-d'),
                'gross_salary' => $grossSalary,
                'net_salary' => $result['net_salary'],
                'calculation_hash' => $calculationHash,
            ],
            null, // user (will be determined from context)
            $employee->business
        );

        return $result;
    }

    /**
     * Generate SHA256 hash of calculation inputs
     * Uses deterministic sorting to ensure consistent hashing
     */
    protected function generateCalculationHash(array $inputs): string
    {
        // Recursively sort arrays to ensure consistent hashing
        $sorted = $this->recursiveSort($inputs);
        $json = json_encode($sorted, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);

        return hash('sha256', $json);
    }

    /**
     * Recursively sort arrays for consistent hashing
     */
    protected function recursiveSort(array $array): array
    {
        ksort($array);

        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $array[$key] = $this->recursiveSort($value);
            }
        }

        return $array;
    }

    /**
     * Get current calculation version
     * Increment this when calculation logic changes
     */
    protected function getCalculationVersion(): int
    {
        return (int) config('payroll.calculation_version', 1);
    }

    /**
     * Validate that a payroll job's calculation is correct
     *
     * @return array Validation result
     */
    public function validateCalculation(PayrollJob $payrollJob): array
    {
        $errors = [];
        $warnings = [];

        // Check if employee still exists
        $employee = $payrollJob->employee;
        if (! $employee) {
            $errors[] = 'Employee not found';
        } else {
            // Verify net salary calculation
            $expectedNet = $payrollJob->gross_salary
                - $payrollJob->paye_amount
                - $payrollJob->uif_amount;

            // Add adjustments
            $adjustments = $payrollJob->adjustments ?? [];
            $totalAdditions = 0;
            $totalDeductions = 0;

            foreach ($adjustments as $adjustment) {
                if (($adjustment['adjustment_type'] ?? 'deduction') === 'deduction') {
                    $totalDeductions += $adjustment['amount'] ?? 0;
                } else {
                    $totalAdditions += $adjustment['amount'] ?? 0;
                }
            }

            $expectedNet = $expectedNet + $totalAdditions - $totalDeductions;
            $expectedNet = max(0, $expectedNet); // Negative protection

            // Allow small rounding differences (0.01)
            $difference = abs($expectedNet - $payrollJob->net_salary);
            if ($difference > 0.01) {
                $errors[] = sprintf(
                    'Net salary mismatch: expected %.2f, got %.2f (difference: %.2f)',
                    $expectedNet,
                    $payrollJob->net_salary,
                    $difference
                );
            }

            // Verify calculation version compatibility
            $currentVersion = $this->getCalculationVersion();
            $jobVersion = $payrollJob->calculation_version ?? 1;
            if ($jobVersion !== $currentVersion) {
                $warnings[] = sprintf(
                    'Calculation version mismatch: job version %d, current version %d. Job may need recalculation.',
                    $jobVersion,
                    $currentVersion
                );
            }

            // Verify calculation hash if present
            if ($payrollJob->calculation_hash) {
                // Recalculate hash from snapshot if available
                if ($payrollJob->calculation_snapshot && $payrollJob->employee_snapshot) {
                    $calculationSnapshot = $payrollJob->calculation_snapshot;
                    $employeeSnapshot = $payrollJob->employee_snapshot;

                    // Use adjustment_inputs if available (contains full adjustment data)
                    // Otherwise fall back to adjustment IDs from snapshot
                    $adjustmentHashData = $payrollJob->adjustment_inputs ?? [];
                    if (empty($adjustmentHashData) && ! empty($calculationSnapshot['adjustments'])) {
                        // Fallback: reconstruct from snapshot adjustments
                        $adjustmentHashData = collect($calculationSnapshot['adjustments'])
                            ->map(function ($adj) {
                                return [
                                    'id' => $adj['id'] ?? null,
                                    'type' => $adj['type'] ?? null,
                                    'amount' => $adj['amount'] ?? null,
                                    'adjustment_type' => $adj['adjustment_type'] ?? null,
                                    'calculated_amount' => $adj['calculated_amount'] ?? null,
                                ];
                            })
                            ->sortBy('id')
                            ->values()
                            ->toArray();
                    }

                    // Use employee ID from snapshot, not current employee ID
                    // Include all calculation inputs for hash validation
                    $hashInputs = [
                        'employee_id' => $employeeSnapshot['id'] ?? $employee->id,
                        'gross_salary' => $calculationSnapshot['gross_salary'] ?? $payrollJob->gross_salary,
                        'uif_exempt' => $calculationSnapshot['uif_exempt'] ?? false,
                        'period_start' => $payrollJob->pay_period_start->format('Y-m-d'),
                        'period_end' => $payrollJob->pay_period_end->format('Y-m-d'),
                        'adjustments' => $adjustmentHashData, // Full adjustment data, not just IDs
                        'tax_year' => $payrollJob->pay_period_start->year,
                        'calculation_version' => $jobVersion,
                    ];
                    $expectedHash = $this->generateCalculationHash($hashInputs);

                    if ($expectedHash !== $payrollJob->calculation_hash) {
                        $warnings[] = 'Calculation hash mismatch - calculation inputs may have changed or calculation logic updated';
                    }
                } else {
                    $warnings[] = 'Missing calculation or employee snapshot - cannot fully verify calculation hash';
                }
            } else {
                $warnings[] = 'No calculation hash present - cannot verify calculation integrity';
            }

            // Check if employee data has changed since calculation
            if ($payrollJob->employee_snapshot) {
                $snapshot = $payrollJob->employee_snapshot;
                if ($employee->gross_salary != ($snapshot['gross_salary'] ?? null)) {
                    $warnings[] = 'Employee gross salary has changed since calculation';
                }
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }
}
