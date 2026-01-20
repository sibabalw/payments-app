<?php

namespace App\Services;

use App\Models\Business;
use App\Models\ComplianceSubmission;
use App\Models\Employee;
use App\Models\PayrollJob;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class IRP5Service
{
    /**
     * SARS Income Source Codes
     */
    private const SOURCE_CODES = [
        'salary' => '3601', // Normal Income
        'bonus' => '3605', // Bonus
        'commission' => '3606', // Commission
        'overtime' => '3607', // Overtime
        'allowance' => '3701', // Travel Allowance
        'fringe_benefit' => '3801', // Fringe Benefits
    ];

    /**
     * SARS Deduction Codes
     */
    private const DEDUCTION_CODES = [
        'paye' => '4102', // PAYE
        'uif' => '4141', // UIF
        'pension' => '4001', // Pension Fund
        'medical' => '4005', // Medical Aid
        'retirement' => '4472', // Retirement Annuity
    ];

    /**
     * Get South African tax year for a given date
     * SA tax year runs from 1 March to end of February
     */
    public function getTaxYear(?Carbon $date = null): string
    {
        $date = $date ?? now();

        // If we're in Jan/Feb, we're still in the previous tax year
        if ($date->month <= 2) {
            $startYear = $date->year - 1;
        } else {
            $startYear = $date->year;
        }

        $endYear = $startYear + 1;

        return "{$startYear}/{$endYear}";
    }

    /**
     * Get tax year date range
     */
    public function getTaxYearDates(string $taxYear): array
    {
        [$startYear, $endYear] = explode('/', $taxYear);

        return [
            'start' => Carbon::create($startYear, 3, 1)->startOfDay(),
            'end' => Carbon::create($endYear, 2, 28)->endOfDay(),
        ];
    }

    /**
     * Generate IRP5 certificate data for an employee
     */
    public function generateIRP5(Employee $employee, string $taxYear): array
    {
        $dates = $this->getTaxYearDates($taxYear);
        $business = $employee->business;

        // Get all payroll jobs for the employee in the tax year
        $payrollJobs = PayrollJob::query()
            ->where('employee_id', $employee->id)
            ->where('status', 'succeeded')
            ->where(function ($q) use ($dates) {
                $q->whereBetween('pay_period_start', [$dates['start'], $dates['end']])
                    ->orWhereBetween('pay_period_end', [$dates['start'], $dates['end']]);
            })
            ->orderBy('pay_period_start')
            ->get();

        if ($payrollJobs->isEmpty()) {
            return [
                'error' => 'No payroll records found for this employee in the specified tax year.',
            ];
        }

        // Calculate totals
        $totalGross = $payrollJobs->sum('gross_salary');
        $totalPaye = $payrollJobs->sum('paye_amount');
        $totalUif = $payrollJobs->sum('uif_amount');

        // Calculate custom deductions
        $customDeductionsTotals = [];
        foreach ($payrollJobs as $job) {
            foreach ($job->custom_deductions ?? [] as $deduction) {
                $name = $deduction['name'] ?? 'Other';
                if (! isset($customDeductionsTotals[$name])) {
                    $customDeductionsTotals[$name] = 0;
                }
                $customDeductionsTotals[$name] += $deduction['amount'] ?? 0;
            }
        }

        // Build income sources with SARS codes
        $incomeSources = [
            [
                'code' => self::SOURCE_CODES['salary'],
                'description' => 'Gross Remuneration',
                'amount' => round($totalGross, 2),
            ],
        ];

        // Build deductions with SARS codes
        $deductions = [
            [
                'code' => self::DEDUCTION_CODES['paye'],
                'description' => 'PAYE',
                'amount' => round($totalPaye, 2),
            ],
            [
                'code' => self::DEDUCTION_CODES['uif'],
                'description' => 'UIF',
                'amount' => round($totalUif, 2),
            ],
        ];

        // Add custom deductions (pension, medical, etc.)
        foreach ($customDeductionsTotals as $name => $amount) {
            $code = $this->mapDeductionToSarsCode($name);
            $deductions[] = [
                'code' => $code,
                'description' => $name,
                'amount' => round($amount, 2),
            ];
        }

        $totalDeductions = $totalPaye + $totalUif + array_sum($customDeductionsTotals);

        // Get employment period within tax year
        $firstPayroll = $payrollJobs->first();
        $lastPayroll = $payrollJobs->last();
        $employmentStart = $firstPayroll->pay_period_start ?? $employee->start_date ?? $dates['start'];
        $employmentEnd = $lastPayroll->pay_period_end ?? $dates['end'];

        // Ensure dates are within tax year
        if ($employmentStart < $dates['start']) {
            $employmentStart = $dates['start'];
        }
        if ($employmentEnd > $dates['end']) {
            $employmentEnd = $dates['end'];
        }

        return [
            'certificate_number' => $this->generateCertificateNumber($business, $employee, $taxYear),
            'tax_year' => $taxYear,
            'employee' => [
                'name' => $employee->name,
                'id_number' => $employee->id_number,
                'tax_number' => $employee->tax_number,
                'email' => $employee->email,
                'employment_start' => $employmentStart instanceof Carbon
                    ? $employmentStart->format('Y-m-d')
                    : $employmentStart,
                'employment_end' => $employmentEnd instanceof Carbon
                    ? $employmentEnd->format('Y-m-d')
                    : $employmentEnd,
            ],
            'employer' => [
                'name' => $business->name,
                'trading_name' => $business->name,
                'registration_number' => $business->registration_number,
                'paye_reference' => $business->tax_id,
                'address' => $this->formatBusinessAddress($business),
            ],
            'income' => [
                'sources' => $incomeSources,
                'total' => round($totalGross, 2),
            ],
            'deductions' => [
                'items' => $deductions,
                'total' => round($totalDeductions, 2),
            ],
            'summary' => [
                'gross_remuneration' => round($totalGross, 2),
                'total_deductions' => round($totalDeductions, 2),
                'taxable_income' => round($totalGross, 2), // Simplified - could be different with exemptions
                'paye_deducted' => round($totalPaye, 2),
                'periods_paid' => $payrollJobs->count(),
            ],
            'generated_at' => now()->toDateTimeString(),
        ];
    }

    /**
     * Generate bulk IRP5 certificates for all employees
     */
    public function generateBulkIRP5(Business $business, string $taxYear): Collection
    {
        $dates = $this->getTaxYearDates($taxYear);

        // Get all employees who had payroll in this tax year
        $employeeIds = PayrollJob::query()
            ->whereHas('payrollSchedule', function ($q) use ($business) {
                $q->where('business_id', $business->id);
            })
            ->where('status', 'succeeded')
            ->where(function ($q) use ($dates) {
                $q->whereBetween('pay_period_start', [$dates['start'], $dates['end']])
                    ->orWhereBetween('pay_period_end', [$dates['start'], $dates['end']]);
            })
            ->distinct()
            ->pluck('employee_id');

        $employees = Employee::whereIn('id', $employeeIds)->get();

        return $employees->map(function ($employee) use ($taxYear) {
            return $this->generateIRP5($employee, $taxYear);
        });
    }

    /**
     * Save IRP5 submission
     */
    public function saveIRP5Submission(Employee $employee, string $taxYear, array $data): ComplianceSubmission
    {
        return ComplianceSubmission::updateOrCreate(
            [
                'business_id' => $employee->business_id,
                'employee_id' => $employee->id,
                'type' => 'irp5',
                'period' => $taxYear,
            ],
            [
                'data' => $data,
                'status' => 'generated',
            ]
        );
    }

    /**
     * Generate IRP5 PDF
     */
    public function generateIRP5Pdf(array $data): \Barryvdh\DomPDF\PDF
    {
        return Pdf::loadView('compliance.irp5-pdf', ['data' => $data])
            ->setPaper('a4', 'portrait');
    }

    /**
     * Get employees with IRP5 status for a tax year
     */
    public function getEmployeesWithIRP5Status(Business $business, string $taxYear): Collection
    {
        $dates = $this->getTaxYearDates($taxYear);

        // Get all employees with payroll in tax year
        $employeeIds = PayrollJob::query()
            ->whereHas('payrollSchedule', function ($q) use ($business) {
                $q->where('business_id', $business->id);
            })
            ->where('status', 'succeeded')
            ->where(function ($q) use ($dates) {
                $q->whereBetween('pay_period_start', [$dates['start'], $dates['end']])
                    ->orWhereBetween('pay_period_end', [$dates['start'], $dates['end']]);
            })
            ->distinct()
            ->pluck('employee_id');

        $employees = Employee::whereIn('id', $employeeIds)
            ->with(['business'])
            ->get();

        // Get existing IRP5 submissions with IDs
        $submissions = ComplianceSubmission::query()
            ->where('business_id', $business->id)
            ->where('type', 'irp5')
            ->where('period', $taxYear)
            ->get()
            ->keyBy('employee_id');

        return $employees->map(function ($employee) use ($submissions, $dates) {
            // Get summary data for employee
            $payrollJobs = PayrollJob::query()
                ->where('employee_id', $employee->id)
                ->where('status', 'succeeded')
                ->where(function ($q) use ($dates) {
                    $q->whereBetween('pay_period_start', [$dates['start'], $dates['end']])
                        ->orWhereBetween('pay_period_end', [$dates['start'], $dates['end']]);
                })
                ->get();

            $submission = $submissions->get($employee->id);

            return [
                'id' => $employee->id,
                'name' => $employee->name,
                'id_number' => $employee->id_number,
                'tax_number' => $employee->tax_number,
                'total_gross' => round($payrollJobs->sum('gross_salary'), 2),
                'total_paye' => round($payrollJobs->sum('paye_amount'), 2),
                'periods' => $payrollJobs->count(),
                'irp5_status' => $submission?->status ?? 'pending',
                'submission_id' => $submission?->id,
            ];
        });
    }

    /**
     * Get available tax years for a business
     */
    public function getAvailableTaxYears(Business $business): Collection
    {
        // Get earliest and latest payroll dates
        $dates = PayrollJob::query()
            ->whereHas('payrollSchedule', function ($q) use ($business) {
                $q->where('business_id', $business->id);
            })
            ->where('status', 'succeeded')
            ->selectRaw('MIN(pay_period_start) as earliest, MAX(pay_period_end) as latest')
            ->first();

        if (! $dates->earliest) {
            return collect([$this->getTaxYear()]);
        }

        $earliest = Carbon::parse($dates->earliest);
        $latest = Carbon::parse($dates->latest);

        $taxYears = collect();
        $current = $earliest->copy();

        while ($current <= $latest) {
            $taxYears->push($this->getTaxYear($current));
            $current->addYear();
        }

        return $taxYears->unique()->values();
    }

    /**
     * Generate unique certificate number
     */
    private function generateCertificateNumber(Business $business, Employee $employee, string $taxYear): string
    {
        $yearPart = str_replace('/', '', $taxYear);
        $businessPart = str_pad($business->id, 4, '0', STR_PAD_LEFT);
        $employeePart = str_pad($employee->id, 6, '0', STR_PAD_LEFT);

        return "IRP5-{$yearPart}-{$businessPart}-{$employeePart}";
    }

    /**
     * Format business address for certificate
     */
    private function formatBusinessAddress(Business $business): string
    {
        $parts = array_filter([
            $business->street_address,
            $business->city,
            $business->province,
            $business->postal_code,
        ]);

        return implode(', ', $parts) ?: 'Address not provided';
    }

    /**
     * Map deduction name to SARS code
     */
    private function mapDeductionToSarsCode(string $name): string
    {
        $nameLower = strtolower($name);

        if (str_contains($nameLower, 'pension') || str_contains($nameLower, 'provident')) {
            return self::DEDUCTION_CODES['pension'];
        }

        if (str_contains($nameLower, 'medical') || str_contains($nameLower, 'health')) {
            return self::DEDUCTION_CODES['medical'];
        }

        if (str_contains($nameLower, 'retirement') || str_contains($nameLower, 'annuity')) {
            return self::DEDUCTION_CODES['retirement'];
        }

        return '4999'; // Other deductions
    }
}
