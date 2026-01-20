<?php

namespace App\Services;

use App\Models\Business;
use App\Models\ComplianceSubmission;
use App\Models\PayrollJob;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class EMP201Service
{
    /**
     * UIF employer contribution rate (same as employee)
     */
    private const UIF_EMPLOYER_RATE = 0.01;

    /**
     * Generate EMP201 monthly reconciliation data
     */
    public function generateEMP201(Business $business, string $period): array
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

        // Calculate totals
        $totalGross = $payrollJobs->sum('gross_salary');
        $totalPaye = $payrollJobs->sum('paye_amount');
        $totalUifEmployee = $payrollJobs->sum('uif_amount');
        $totalUifEmployer = $payrollJobs->sum('uif_amount'); // Employer matches employee
        $totalSdl = $payrollJobs->sum('sdl_amount');

        // Employee breakdown for detailed report
        $employeeBreakdown = $payrollJobs->groupBy('employee_id')->map(function ($jobs, $employeeId) {
            $employee = $jobs->first()->employee;

            return [
                'employee_id' => $employeeId,
                'employee_name' => $employee->name ?? 'N/A',
                'id_number' => $employee->id_number ?? '',
                'tax_number' => $employee->tax_number ?? '',
                'gross_salary' => round($jobs->sum('gross_salary'), 2),
                'paye' => round($jobs->sum('paye_amount'), 2),
                'uif_employee' => round($jobs->sum('uif_amount'), 2),
                'uif_employer' => round($jobs->sum('uif_amount'), 2),
                'sdl' => round($jobs->sum('sdl_amount'), 2),
            ];
        })->values();

        return [
            'business' => [
                'name' => $business->name,
                'registration_number' => $business->registration_number,
                'paye_reference' => $business->tax_id, // Tax ID as PAYE reference
                'sdl_reference' => $business->tax_id,
                'uif_reference' => $business->tax_id,
            ],
            'period' => $period,
            'period_display' => $date->format('F Y'),
            'submission_deadline' => $date->copy()->addMonth()->day(7)->format('Y-m-d'),
            'totals' => [
                'employees_count' => $employeeBreakdown->count(),
                'total_gross' => round($totalGross, 2),
                'total_paye' => round($totalPaye, 2),
                'total_uif_employee' => round($totalUifEmployee, 2),
                'total_uif_employer' => round($totalUifEmployer, 2),
                'total_uif' => round($totalUifEmployee + $totalUifEmployer, 2),
                'total_sdl' => round($totalSdl, 2),
                'total_liability' => round($totalPaye + $totalUifEmployee + $totalUifEmployer + $totalSdl, 2),
            ],
            'employees' => $employeeBreakdown->toArray(),
            'generated_at' => now()->toDateTimeString(),
        ];
    }

    /**
     * Save or update an EMP201 submission
     */
    public function saveEMP201Submission(Business $business, string $period, array $data): ComplianceSubmission
    {
        return ComplianceSubmission::updateOrCreate(
            [
                'business_id' => $business->id,
                'type' => 'emp201',
                'period' => $period,
            ],
            [
                'data' => $data,
                'status' => 'generated',
            ]
        );
    }

    /**
     * Generate SARS-compatible CSV for EMP201
     */
    public function generateEMP201Csv(array $data): string
    {
        $output = fopen('php://temp', 'r+');

        // Add BOM for Excel compatibility
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

        // Header information
        fputcsv($output, ['EMP201 Monthly Employer Declaration']);
        fputcsv($output, ['Generated for SARS eFiling']);
        fputcsv($output, []);
        fputcsv($output, ['Business Details']);
        fputcsv($output, ['Name', $data['business']['name'] ?? '']);
        fputcsv($output, ['Registration Number', $data['business']['registration_number'] ?? '']);
        fputcsv($output, ['PAYE Reference', $data['business']['paye_reference'] ?? '']);
        fputcsv($output, ['SDL Reference', $data['business']['sdl_reference'] ?? '']);
        fputcsv($output, ['UIF Reference', $data['business']['uif_reference'] ?? '']);
        fputcsv($output, []);

        // Period information
        fputcsv($output, ['Period', $data['period_display'] ?? '']);
        fputcsv($output, ['Submission Deadline', $data['submission_deadline'] ?? '']);
        fputcsv($output, []);

        // Summary totals
        fputcsv($output, ['SUMMARY']);
        fputcsv($output, ['Number of Employees', $data['totals']['employees_count'] ?? 0]);
        fputcsv($output, ['Total Gross Remuneration', number_format($data['totals']['total_gross'] ?? 0, 2)]);
        fputcsv($output, []);
        fputcsv($output, ['TAX LIABILITIES']);
        fputcsv($output, ['PAYE', number_format($data['totals']['total_paye'] ?? 0, 2)]);
        fputcsv($output, ['UIF (Employee)', number_format($data['totals']['total_uif_employee'] ?? 0, 2)]);
        fputcsv($output, ['UIF (Employer)', number_format($data['totals']['total_uif_employer'] ?? 0, 2)]);
        fputcsv($output, ['UIF Total', number_format($data['totals']['total_uif'] ?? 0, 2)]);
        fputcsv($output, ['SDL', number_format($data['totals']['total_sdl'] ?? 0, 2)]);
        fputcsv($output, []);
        fputcsv($output, ['TOTAL LIABILITY', number_format($data['totals']['total_liability'] ?? 0, 2)]);
        fputcsv($output, []);

        // Employee details
        fputcsv($output, ['EMPLOYEE DETAILS']);
        fputcsv($output, [
            'Tax Number',
            'ID Number',
            'Name',
            'Gross Salary',
            'PAYE',
            'UIF (Employee)',
            'UIF (Employer)',
            'SDL',
        ]);

        foreach ($data['employees'] ?? [] as $employee) {
            fputcsv($output, [
                $employee['tax_number'] ?? '',
                $employee['id_number'] ?? '',
                $employee['employee_name'] ?? '',
                number_format($employee['gross_salary'] ?? 0, 2),
                number_format($employee['paye'] ?? 0, 2),
                number_format($employee['uif_employee'] ?? 0, 2),
                number_format($employee['uif_employer'] ?? 0, 2),
                number_format($employee['sdl'] ?? 0, 2),
            ]);
        }

        rewind($output);
        $content = stream_get_contents($output);
        fclose($output);

        return $content;
    }

    /**
     * Get pending EMP201 periods for a business
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
            ->where('type', 'emp201')
            ->whereIn('status', ['generated', 'submitted'])
            ->pluck('period');

        // Return periods that haven't been submitted
        return $activeMonths->diff($submittedPeriods)->values();
    }

    /**
     * Get EMP201 checklist for a period
     */
    public function getSubmissionChecklist(array $data): array
    {
        return [
            [
                'item' => 'PAYE Calculation',
                'amount' => $data['totals']['total_paye'] ?? 0,
                'status' => ($data['totals']['total_paye'] ?? 0) >= 0 ? 'ready' : 'warning',
            ],
            [
                'item' => 'UIF Employee Contributions',
                'amount' => $data['totals']['total_uif_employee'] ?? 0,
                'status' => ($data['totals']['total_uif_employee'] ?? 0) >= 0 ? 'ready' : 'warning',
            ],
            [
                'item' => 'UIF Employer Contributions',
                'amount' => $data['totals']['total_uif_employer'] ?? 0,
                'status' => ($data['totals']['total_uif_employer'] ?? 0) >= 0 ? 'ready' : 'warning',
            ],
            [
                'item' => 'SDL (Skills Development Levy)',
                'amount' => $data['totals']['total_sdl'] ?? 0,
                'status' => ($data['totals']['total_sdl'] ?? 0) >= 0 ? 'ready' : 'warning',
            ],
            [
                'item' => 'Total Tax Liability',
                'amount' => $data['totals']['total_liability'] ?? 0,
                'status' => 'ready',
            ],
        ];
    }
}
