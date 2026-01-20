<?php

namespace App\Services;

use App\Models\Business;
use App\Models\ComplianceSubmission;
use App\Models\Employee;
use App\Models\PayrollJob;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class UIFDeclarationService
{
    /**
     * UIF contribution rate (employee portion)
     */
    private const UIF_EMPLOYEE_RATE = 0.01;

    /**
     * UIF contribution rate (employer portion)
     */
    private const UIF_EMPLOYER_RATE = 0.01;

    /**
     * UIF earnings ceiling per month
     */
    private const UIF_EARNINGS_CEILING = 17712.00;

    public function __construct(
        private readonly SouthAfricanTaxService $taxService
    ) {}

    /**
     * Generate monthly UI-19 declaration data for a business
     */
    public function generateMonthlyUI19(Business $business, string $period): array
    {
        $date = Carbon::createFromFormat('Y-m', $period);
        $startDate = $date->copy()->startOfMonth();
        $endDate = $date->copy()->endOfMonth();

        // Get all payroll jobs for the period
        $payrollJobs = PayrollJob::query()
            ->whereHas('payrollSchedule', function ($q) use ($business) {
                $q->where('business_id', $business->id);
            })
            ->where('status', 'succeeded')
            ->where(function ($q) use ($startDate, $endDate) {
                $q->whereBetween('pay_period_start', [$startDate, $endDate])
                    ->orWhereBetween('pay_period_end', [$startDate, $endDate]);
            })
            ->with('employee')
            ->get();

        // Group by employee and calculate contributions
        $employeeContributions = $payrollJobs->groupBy('employee_id')->map(function ($jobs, $employeeId) {
            $employee = $jobs->first()->employee;
            $totalGross = $jobs->sum('gross_salary');
            $totalUifEmployee = $jobs->sum('uif_amount');

            // Calculate employer UIF (same as employee)
            $uifBase = min($totalGross, self::UIF_EARNINGS_CEILING * $jobs->count());
            $totalUifEmployer = round($uifBase * self::UIF_EMPLOYER_RATE, 2);

            return [
                'employee_id' => $employeeId,
                'employee_name' => $employee->name ?? 'N/A',
                'id_number' => $employee->id_number ?? '',
                'gross_remuneration' => round($totalGross, 2),
                'uif_employee' => round($totalUifEmployee, 2),
                'uif_employer' => $totalUifEmployer,
                'total_uif' => round($totalUifEmployee + $totalUifEmployer, 2),
                'days_worked' => $jobs->count() * 30, // Approximate
            ];
        })->values();

        // Calculate totals
        $totals = [
            'total_employees' => $employeeContributions->count(),
            'total_gross_remuneration' => $employeeContributions->sum('gross_remuneration'),
            'total_uif_employee' => $employeeContributions->sum('uif_employee'),
            'total_uif_employer' => $employeeContributions->sum('uif_employer'),
            'total_uif_contribution' => $employeeContributions->sum('total_uif'),
        ];

        return [
            'business' => [
                'name' => $business->name,
                'registration_number' => $business->registration_number,
                'uif_reference' => $business->tax_id, // Using tax_id as UIF reference
            ],
            'period' => $period,
            'period_display' => $date->format('F Y'),
            'employees' => $employeeContributions->toArray(),
            'totals' => $totals,
            'generated_at' => now()->toDateTimeString(),
        ];
    }

    /**
     * Generate new employee registration declaration (UI-8)
     */
    public function generateEmployeeRegistration(Employee $employee): array
    {
        return [
            'type' => 'registration',
            'employee' => [
                'name' => $employee->name,
                'id_number' => $employee->id_number,
                'tax_number' => $employee->tax_number,
                'email' => $employee->email,
                'employment_type' => $employee->employment_type,
                'start_date' => $employee->start_date?->format('Y-m-d'),
                'gross_salary' => $employee->gross_salary,
            ],
            'business' => [
                'name' => $employee->business->name,
                'registration_number' => $employee->business->registration_number,
                'uif_reference' => $employee->business->tax_id,
            ],
            'generated_at' => now()->toDateTimeString(),
        ];
    }

    /**
     * Generate employee termination declaration (UI-8 termination)
     */
    public function generateTerminationDeclaration(
        Employee $employee,
        string $terminationDate,
        string $reason = 'resignation'
    ): array {
        // Get total earnings and UIF contributions during employment
        $payrollJobs = PayrollJob::query()
            ->where('employee_id', $employee->id)
            ->where('status', 'succeeded')
            ->get();

        $totalGross = $payrollJobs->sum('gross_salary');
        $totalUifContributed = $payrollJobs->sum('uif_amount') * 2; // Employee + employer portions

        return [
            'type' => 'termination',
            'employee' => [
                'name' => $employee->name,
                'id_number' => $employee->id_number,
                'tax_number' => $employee->tax_number,
                'start_date' => $employee->start_date?->format('Y-m-d'),
                'termination_date' => $terminationDate,
                'reason' => $reason,
            ],
            'employment_summary' => [
                'total_gross_earnings' => round($totalGross, 2),
                'total_uif_contributed' => round($totalUifContributed, 2),
                'months_employed' => $payrollJobs->count(),
            ],
            'business' => [
                'name' => $employee->business->name,
                'registration_number' => $employee->business->registration_number,
                'uif_reference' => $employee->business->tax_id,
            ],
            'generated_at' => now()->toDateTimeString(),
        ];
    }

    /**
     * Save or update a UI-19 submission
     */
    public function saveUI19Submission(Business $business, string $period, array $data): ComplianceSubmission
    {
        return ComplianceSubmission::updateOrCreate(
            [
                'business_id' => $business->id,
                'type' => 'ui19',
                'period' => $period,
            ],
            [
                'data' => $data,
                'status' => 'generated',
            ]
        );
    }

    /**
     * Generate CSV content for UI-19 submission
     */
    public function generateUI19Csv(array $data): string
    {
        $output = fopen('php://temp', 'r+');

        // Add BOM for Excel compatibility
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

        // Header
        fputcsv($output, ['UIF UI-19 Monthly Declaration']);
        fputcsv($output, ['Business Name', $data['business']['name'] ?? '']);
        fputcsv($output, ['UIF Reference', $data['business']['uif_reference'] ?? '']);
        fputcsv($output, ['Period', $data['period_display'] ?? '']);
        fputcsv($output, []);

        // Employee contributions header
        fputcsv($output, [
            'ID Number',
            'Employee Name',
            'Gross Remuneration',
            'Employee UIF',
            'Employer UIF',
            'Total UIF',
        ]);

        // Employee rows
        foreach ($data['employees'] ?? [] as $employee) {
            fputcsv($output, [
                $employee['id_number'] ?? '',
                $employee['employee_name'] ?? '',
                number_format($employee['gross_remuneration'] ?? 0, 2),
                number_format($employee['uif_employee'] ?? 0, 2),
                number_format($employee['uif_employer'] ?? 0, 2),
                number_format($employee['total_uif'] ?? 0, 2),
            ]);
        }

        // Totals
        fputcsv($output, []);
        fputcsv($output, ['TOTALS']);
        fputcsv($output, ['Total Employees', $data['totals']['total_employees'] ?? 0]);
        fputcsv($output, ['Total Gross Remuneration', number_format($data['totals']['total_gross_remuneration'] ?? 0, 2)]);
        fputcsv($output, ['Total Employee UIF', number_format($data['totals']['total_uif_employee'] ?? 0, 2)]);
        fputcsv($output, ['Total Employer UIF', number_format($data['totals']['total_uif_employer'] ?? 0, 2)]);
        fputcsv($output, ['Total UIF Contribution', number_format($data['totals']['total_uif_contribution'] ?? 0, 2)]);

        rewind($output);
        $content = stream_get_contents($output);
        fclose($output);

        return $content;
    }

    /**
     * Get pending UI-19 periods for a business
     */
    public function getPendingPeriods(Business $business): Collection
    {
        // Get all months with payroll activity
        $activeMonths = PayrollJob::query()
            ->whereHas('payrollSchedule', function ($q) use ($business) {
                $q->where('business_id', $business->id);
            })
            ->where('status', 'succeeded')
            ->selectRaw("DATE_FORMAT(pay_period_start, '%Y-%m') as period")
            ->distinct()
            ->pluck('period');

        // Get submitted periods
        $submittedPeriods = ComplianceSubmission::query()
            ->where('business_id', $business->id)
            ->where('type', 'ui19')
            ->whereIn('status', ['generated', 'submitted'])
            ->pluck('period');

        // Return periods that haven't been submitted
        return $activeMonths->diff($submittedPeriods)->values();
    }
}
