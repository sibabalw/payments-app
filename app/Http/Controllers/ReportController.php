<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\Employee;
use App\Models\PaymentJob;
use App\Models\PayrollJob;
use Barryvdh\DomPDF\Facade\Pdf as PDF;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    /**
     * Display reports index page
     */
    public function index(Request $request): Response
    {
        $businessId = $request->get('business_id') ?? Auth::user()->current_business_id ?? session('current_business_id');
        $reportType = $request->get('report_type', 'payroll_summary');
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');

        $businesses = Auth::user()->businesses()->get();

        $data = [
            'report_type' => $reportType,
            'business_id' => $businessId,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'businesses' => $businesses,
        ];

        // Generate report data based on type
        switch ($reportType) {
            case 'payroll_summary':
                $data['report'] = $this->getPayrollSummary($businessId, $startDate, $endDate);
                break;
            case 'payroll_by_employee':
                $data['report'] = $this->getPayrollByEmployee($businessId, $startDate, $endDate);
                break;
            case 'tax_summary':
                $data['report'] = $this->getTaxSummary($businessId, $startDate, $endDate);
                break;
            case 'deductions_summary':
                $data['report'] = $this->getDeductionsSummary($businessId, $startDate, $endDate);
                break;
            case 'payment_summary':
                $data['report'] = $this->getPaymentSummary($businessId, $startDate, $endDate);
                break;
            case 'employee_earnings':
                $data['report'] = $this->getEmployeeEarnings($businessId, $startDate, $endDate);
                break;
            default:
                $data['report'] = $this->getPayrollSummary($businessId, $startDate, $endDate);
        }

        return Inertia::render('reports/index', $data);
    }

    /**
     * Get payroll summary report
     */
    private function getPayrollSummary(?string $businessId, ?string $startDate, ?string $endDate): array
    {
        $query = PayrollJob::query()
            ->where('status', 'succeeded')
            ->with(['employee', 'payrollSchedule.business']);

        if ($businessId) {
            $query->whereHas('payrollSchedule', function ($q) use ($businessId) {
                $q->where('business_id', $businessId);
            });
        } else {
            $userBusinessIds = Auth::user()->businesses()->pluck('businesses.id');
            $query->whereHas('payrollSchedule', function ($q) use ($userBusinessIds) {
                $q->whereIn('business_id', $userBusinessIds);
            });
        }

        if ($startDate) {
            $query->whereDate('pay_period_start', '>=', $startDate);
        }
        if ($endDate) {
            $query->whereDate('pay_period_end', '<=', $endDate);
        }

        $jobs = $query->get();

        return [
            'total_jobs' => $jobs->count(),
            'total_gross' => $jobs->sum('gross_salary'),
            'total_paye' => $jobs->sum('paye_amount'),
            'total_uif' => $jobs->sum('uif_amount'),
            'total_sdl' => $jobs->sum('sdl_amount'),
            'total_custom_deductions' => $jobs->sum(function ($job) {
                return collect($job->custom_deductions ?? [])->sum('amount');
            }),
            'total_net' => $jobs->sum('net_salary'),
            'jobs' => $jobs->map(function ($job) {
                return [
                    'id' => $job->id,
                    'employee_name' => $job->employee->name ?? 'N/A',
                    'gross_salary' => $job->gross_salary,
                    'paye_amount' => $job->paye_amount,
                    'uif_amount' => $job->uif_amount,
                    'sdl_amount' => $job->sdl_amount,
                    'custom_deductions' => $job->custom_deductions ?? [],
                    'custom_deductions_total' => collect($job->custom_deductions ?? [])->sum('amount'),
                    'net_salary' => $job->net_salary,
                    'pay_period_start' => $job->pay_period_start?->format('Y-m-d'),
                    'pay_period_end' => $job->pay_period_end?->format('Y-m-d'),
                    'processed_at' => $job->processed_at?->format('Y-m-d H:i:s'),
                ];
            }),
        ];
    }

    /**
     * Get payroll by employee report
     */
    private function getPayrollByEmployee(?string $businessId, ?string $startDate, ?string $endDate): array
    {
        $query = PayrollJob::query()
            ->where('status', 'succeeded')
            ->with(['employee', 'payrollSchedule.business']);

        if ($businessId) {
            $query->whereHas('payrollSchedule', function ($q) use ($businessId) {
                $q->where('business_id', $businessId);
            });
        } else {
            $userBusinessIds = Auth::user()->businesses()->pluck('businesses.id');
            $query->whereHas('payrollSchedule', function ($q) use ($userBusinessIds) {
                $q->whereIn('business_id', $userBusinessIds);
            });
        }

        if ($startDate) {
            $query->whereDate('pay_period_start', '>=', $startDate);
        }
        if ($endDate) {
            $query->whereDate('pay_period_end', '<=', $endDate);
        }

        $jobs = $query->get();

        $byEmployee = $jobs->groupBy('employee_id')->map(function ($employeeJobs, $employeeId) {
            $employee = $employeeJobs->first()->employee;

            return [
                'employee_id' => $employeeId,
                'employee_name' => $employee->name ?? 'N/A',
                'total_jobs' => $employeeJobs->count(),
                'total_gross' => $employeeJobs->sum('gross_salary'),
                'total_paye' => $employeeJobs->sum('paye_amount'),
                'total_uif' => $employeeJobs->sum('uif_amount'),
                'total_sdl' => $employeeJobs->sum('sdl_amount'),
                'total_custom_deductions' => $employeeJobs->sum(function ($job) {
                    return collect($job->custom_deductions ?? [])->sum('amount');
                }),
                'total_net' => $employeeJobs->sum('net_salary'),
                'jobs' => $employeeJobs->map(function ($job) {
                    return [
                        'id' => $job->id,
                        'gross_salary' => $job->gross_salary,
                        'net_salary' => $job->net_salary,
                        'pay_period_start' => $job->pay_period_start?->format('Y-m-d'),
                        'pay_period_end' => $job->pay_period_end?->format('Y-m-d'),
                    ];
                })->values(),
            ];
        })->values();

        return [
            'employees' => $byEmployee,
            'total_employees' => $byEmployee->count(),
            'summary' => [
                'total_gross' => $byEmployee->sum('total_gross'),
                'total_net' => $byEmployee->sum('total_net'),
                'total_paye' => $byEmployee->sum('total_paye'),
                'total_uif' => $byEmployee->sum('total_uif'),
                'total_sdl' => $byEmployee->sum('total_sdl'),
            ],
        ];
    }

    /**
     * Get tax summary report
     */
    private function getTaxSummary(?string $businessId, ?string $startDate, ?string $endDate): array
    {
        $query = PayrollJob::query()
            ->where('status', 'succeeded')
            ->with(['employee', 'payrollSchedule.business']);

        if ($businessId) {
            $query->whereHas('payrollSchedule', function ($q) use ($businessId) {
                $q->where('business_id', $businessId);
            });
        } else {
            $userBusinessIds = Auth::user()->businesses()->pluck('businesses.id');
            $query->whereHas('payrollSchedule', function ($q) use ($userBusinessIds) {
                $q->whereIn('business_id', $userBusinessIds);
            });
        }

        if ($startDate) {
            $query->whereDate('pay_period_start', '>=', $startDate);
        }
        if ($endDate) {
            $query->whereDate('pay_period_end', '<=', $endDate);
        }

        $jobs = $query->get();

        return [
            'paye' => [
                'total' => $jobs->sum('paye_amount'),
                'count' => $jobs->where('paye_amount', '>', 0)->count(),
                'average' => $jobs->where('paye_amount', '>', 0)->avg('paye_amount') ?? 0,
            ],
            'uif' => [
                'total' => $jobs->sum('uif_amount'),
                'count' => $jobs->where('uif_amount', '>', 0)->count(),
                'average' => $jobs->where('uif_amount', '>', 0)->avg('uif_amount') ?? 0,
            ],
            'sdl' => [
                'total' => $jobs->sum('sdl_amount'),
                'count' => $jobs->where('sdl_amount', '>', 0)->count(),
                'average' => $jobs->where('sdl_amount', '>', 0)->avg('sdl_amount') ?? 0,
            ],
            'total_tax_liability' => $jobs->sum('paye_amount') + $jobs->sum('uif_amount'),
            'total_employer_costs' => $jobs->sum('sdl_amount'),
        ];
    }

    /**
     * Get deductions summary report
     */
    private function getDeductionsSummary(?string $businessId, ?string $startDate, ?string $endDate): array
    {
        $query = PayrollJob::query()
            ->where('status', 'succeeded')
            ->with(['employee', 'payrollSchedule.business']);

        if ($businessId) {
            $query->whereHas('payrollSchedule', function ($q) use ($businessId) {
                $q->where('business_id', $businessId);
            });
        } else {
            $userBusinessIds = Auth::user()->businesses()->pluck('businesses.id');
            $query->whereHas('payrollSchedule', function ($q) use ($userBusinessIds) {
                $q->whereIn('business_id', $userBusinessIds);
            });
        }

        if ($startDate) {
            $query->whereDate('pay_period_start', '>=', $startDate);
        }
        if ($endDate) {
            $query->whereDate('pay_period_end', '<=', $endDate);
        }

        $jobs = $query->get();

        // Aggregate custom deductions by name
        $deductionBreakdown = [];
        foreach ($jobs as $job) {
            foreach ($job->custom_deductions ?? [] as $deduction) {
                $name = $deduction['name'] ?? 'Unknown';
                if (! isset($deductionBreakdown[$name])) {
                    $deductionBreakdown[$name] = [
                        'name' => $name,
                        'type' => $deduction['type'] ?? 'fixed',
                        'total_amount' => 0,
                        'count' => 0,
                    ];
                }
                $deductionBreakdown[$name]['total_amount'] += $deduction['amount'] ?? 0;
                $deductionBreakdown[$name]['count']++;
            }
        }

        return [
            'deductions' => array_values($deductionBreakdown),
            'total_custom_deductions' => $jobs->sum(function ($job) {
                return collect($job->custom_deductions ?? [])->sum('amount');
            }),
            'total_statutory_deductions' => $jobs->sum('paye_amount') + $jobs->sum('uif_amount'),
            'total_all_deductions' => $jobs->sum(function ($job) {
                return $job->paye_amount + $job->uif_amount + collect($job->custom_deductions ?? [])->sum('amount');
            }),
        ];
    }

    /**
     * Get payment summary report
     */
    private function getPaymentSummary(?string $businessId, ?string $startDate, ?string $endDate): array
    {
        $query = PaymentJob::query()
            ->where('status', 'succeeded')
            ->with(['paymentSchedule.business', 'receiver']);

        if ($businessId) {
            $query->whereHas('paymentSchedule', function ($q) use ($businessId) {
                $q->where('business_id', $businessId);
            });
        } else {
            $userBusinessIds = Auth::user()->businesses()->pluck('businesses.id');
            $query->whereHas('paymentSchedule', function ($q) use ($userBusinessIds) {
                $q->whereIn('business_id', $userBusinessIds);
            });
        }

        if ($startDate) {
            $query->whereDate('processed_at', '>=', $startDate);
        }
        if ($endDate) {
            $query->whereDate('processed_at', '<=', $endDate);
        }

        $jobs = $query->get();

        return [
            'total_jobs' => $jobs->count(),
            'total_amount' => $jobs->sum('amount'),
            'total_fees' => $jobs->sum('fee'),
            'jobs' => $jobs->map(function ($job) {
                return [
                    'id' => $job->id,
                    'receiver_name' => $job->receiver->name ?? 'N/A',
                    'amount' => $job->amount,
                    'fee' => $job->fee,
                    'currency' => $job->currency,
                    'processed_at' => $job->processed_at?->format('Y-m-d H:i:s'),
                ];
            }),
        ];
    }

    /**
     * Get employee earnings report
     */
    private function getEmployeeEarnings(?string $businessId, ?string $startDate, ?string $endDate): array
    {
        $query = PayrollJob::query()
            ->where('status', 'succeeded')
            ->with(['employee', 'payrollSchedule.business']);

        if ($businessId) {
            $query->whereHas('payrollSchedule', function ($q) use ($businessId) {
                $q->where('business_id', $businessId);
            });
        } else {
            $userBusinessIds = Auth::user()->businesses()->pluck('businesses.id');
            $query->whereHas('payrollSchedule', function ($q) use ($userBusinessIds) {
                $q->whereIn('business_id', $userBusinessIds);
            });
        }

        if ($startDate) {
            $query->whereDate('pay_period_start', '>=', $startDate);
        }
        if ($endDate) {
            $query->whereDate('pay_period_end', '<=', $endDate);
        }

        $jobs = $query->get();

        $byEmployee = $jobs->groupBy('employee_id')->map(function ($employeeJobs, $employeeId) {
            $employee = $employeeJobs->first()->employee;
            $totalGross = $employeeJobs->sum('gross_salary');
            $totalNet = $employeeJobs->sum('net_salary');

            return [
                'employee_id' => $employeeId,
                'employee_name' => $employee->name ?? 'N/A',
                'employee_email' => $employee->email ?? null,
                'total_payments' => $employeeJobs->count(),
                'total_gross' => $totalGross,
                'total_net' => $totalNet,
                'average_gross' => $employeeJobs->avg('gross_salary'),
                'average_net' => $employeeJobs->avg('net_salary'),
                'total_deductions' => $totalGross - $totalNet,
                'deduction_percentage' => $totalGross > 0 ? (($totalGross - $totalNet) / $totalGross) * 100 : 0,
            ];
        })->values();

        return [
            'employees' => $byEmployee,
            'summary' => [
                'total_employees' => $byEmployee->count(),
                'total_gross' => $byEmployee->sum('total_gross'),
                'total_net' => $byEmployee->sum('total_net'),
                'total_deductions' => $byEmployee->sum('total_deductions'),
            ],
        ];
    }

    /**
     * Export report to CSV
     */
    public function exportCsv(Request $request)
    {
        $businessId = $request->get('business_id');
        $reportType = $request->get('report_type', 'payroll_summary');
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');

        $report = $this->getReportData($reportType, $businessId, $startDate, $endDate);

        $filename = $this->getExportFilename($reportType, $startDate, $endDate, 'csv');

        return response()->streamDownload(function () use ($report, $reportType) {
            $output = fopen('php://output', 'w');

            // Add BOM for Excel compatibility
            fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

            $this->writeCsvData($output, $report, $reportType);

            fclose($output);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * Export report to PDF
     */
    public function exportPdf(Request $request)
    {
        $businessId = $request->get('business_id');
        $reportType = $request->get('report_type', 'payroll_summary');
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');

        $report = $this->getReportData($reportType, $businessId, $startDate, $endDate);
        $selectedBusiness = $businessId ? Business::find($businessId) : null;

        $filename = $this->getExportFilename($reportType, $startDate, $endDate, 'pdf');

        $pdf = PDF::loadView('reports.pdf', [
            'report' => $report,
            'reportType' => $reportType,
            'business' => $selectedBusiness,
            'startDate' => $startDate,
            'endDate' => $endDate,
        ]);

        return $pdf->download($filename);
    }

    /**
     * Export report to Excel (CSV format)
     */
    public function exportExcel(Request $request)
    {
        // For now, Excel export uses CSV format (compatible with Excel)
        return $this->exportCsv($request);
    }

    /**
     * Get report data based on type
     */
    private function getReportData(string $reportType, ?string $businessId, ?string $startDate, ?string $endDate): array
    {
        switch ($reportType) {
            case 'payroll_summary':
                return $this->getPayrollSummary($businessId, $startDate, $endDate);
            case 'payroll_by_employee':
                return $this->getPayrollByEmployee($businessId, $startDate, $endDate);
            case 'tax_summary':
                return $this->getTaxSummary($businessId, $startDate, $endDate);
            case 'deductions_summary':
                return $this->getDeductionsSummary($businessId, $startDate, $endDate);
            case 'payment_summary':
                return $this->getPaymentSummary($businessId, $startDate, $endDate);
            case 'employee_earnings':
                return $this->getEmployeeEarnings($businessId, $startDate, $endDate);
            default:
                return $this->getPayrollSummary($businessId, $startDate, $endDate);
        }
    }

    /**
     * Get export filename
     */
    private function getExportFilename(string $reportType, ?string $startDate, ?string $endDate, string $extension): string
    {
        $dateRange = '';
        if ($startDate && $endDate) {
            $dateRange = '_'.str_replace('-', '', $startDate).'_to_'.str_replace('-', '', $endDate);
        } elseif ($startDate) {
            $dateRange = '_from_'.str_replace('-', '', $startDate);
        } elseif ($endDate) {
            $dateRange = '_until_'.str_replace('-', '', $endDate);
        }

        $reportName = str_replace('_', '-', $reportType);

        return "{$reportName}{$dateRange}_".now()->format('Y-m-d_His').".{$extension}";
    }

    /**
     * Write CSV data based on report type
     */
    private function writeCsvData($output, array $report, string $reportType): void
    {
        switch ($reportType) {
            case 'payroll_summary':
                $this->writePayrollSummaryCsv($output, $report);
                break;
            case 'payroll_by_employee':
                $this->writePayrollByEmployeeCsv($output, $report);
                break;
            case 'tax_summary':
                $this->writeTaxSummaryCsv($output, $report);
                break;
            case 'deductions_summary':
                $this->writeDeductionsSummaryCsv($output, $report);
                break;
            case 'payment_summary':
                $this->writePaymentSummaryCsv($output, $report);
                break;
            case 'employee_earnings':
                $this->writeEmployeeEarningsCsv($output, $report);
                break;
        }
    }

    private function writePayrollSummaryCsv($output, array $report): void
    {
        // Summary row
        fputcsv($output, ['Payroll Summary Report']);
        fputcsv($output, []);
        fputcsv($output, ['Total Jobs', $report['total_jobs'] ?? 0]);
        fputcsv($output, ['Total Gross', number_format($report['total_gross'] ?? 0, 2)]);
        fputcsv($output, ['Total PAYE', number_format($report['total_paye'] ?? 0, 2)]);
        fputcsv($output, ['Total UIF', number_format($report['total_uif'] ?? 0, 2)]);
        fputcsv($output, ['Total Custom Deductions', number_format($report['total_custom_deductions'] ?? 0, 2)]);
        fputcsv($output, ['Total SDL', number_format($report['total_sdl'] ?? 0, 2)]);
        fputcsv($output, ['Total Net', number_format($report['total_net'] ?? 0, 2)]);
        fputcsv($output, []);

        // Details
        if (isset($report['jobs']) && count($report['jobs']) > 0) {
            fputcsv($output, ['Employee', 'Gross Salary', 'PAYE', 'UIF', 'Custom Deductions', 'Net Salary', 'Period Start', 'Period End']);
            foreach ($report['jobs'] as $job) {
                fputcsv($output, [
                    $job['employee_name'] ?? 'N/A',
                    number_format($job['gross_salary'] ?? 0, 2),
                    number_format($job['paye_amount'] ?? 0, 2),
                    number_format($job['uif_amount'] ?? 0, 2),
                    number_format($job['custom_deductions_total'] ?? 0, 2),
                    number_format($job['net_salary'] ?? 0, 2),
                    $job['pay_period_start'] ?? 'N/A',
                    $job['pay_period_end'] ?? 'N/A',
                ]);
            }
        }
    }

    private function writePayrollByEmployeeCsv($output, array $report): void
    {
        fputcsv($output, ['Payroll by Employee Report']);
        fputcsv($output, []);

        if (isset($report['employees']) && count($report['employees']) > 0) {
            fputcsv($output, ['Employee', 'Total Payments', 'Total Gross', 'Total PAYE', 'Total UIF', 'Total Custom Deductions', 'Total Net']);
            foreach ($report['employees'] as $emp) {
                fputcsv($output, [
                    $emp['employee_name'] ?? 'N/A',
                    $emp['total_jobs'] ?? 0,
                    number_format($emp['total_gross'] ?? 0, 2),
                    number_format($emp['total_paye'] ?? 0, 2),
                    number_format($emp['total_uif'] ?? 0, 2),
                    number_format($emp['total_custom_deductions'] ?? 0, 2),
                    number_format($emp['total_net'] ?? 0, 2),
                ]);
            }
        }
    }

    private function writeTaxSummaryCsv($output, array $report): void
    {
        fputcsv($output, ['Tax Summary Report']);
        fputcsv($output, []);
        fputcsv($output, ['Tax Type', 'Total', 'Count', 'Average']);
        fputcsv($output, [
            'PAYE',
            number_format($report['paye']['total'] ?? 0, 2),
            $report['paye']['count'] ?? 0,
            number_format($report['paye']['average'] ?? 0, 2),
        ]);
        fputcsv($output, [
            'UIF',
            number_format($report['uif']['total'] ?? 0, 2),
            $report['uif']['count'] ?? 0,
            number_format($report['uif']['average'] ?? 0, 2),
        ]);
        fputcsv($output, [
            'SDL',
            number_format($report['sdl']['total'] ?? 0, 2),
            $report['sdl']['count'] ?? 0,
            number_format($report['sdl']['average'] ?? 0, 2),
        ]);
        fputcsv($output, []);
        fputcsv($output, ['Total Tax Liability', number_format($report['total_tax_liability'] ?? 0, 2)]);
        fputcsv($output, ['Total Employer Costs', number_format($report['total_employer_costs'] ?? 0, 2)]);
    }

    private function writeDeductionsSummaryCsv($output, array $report): void
    {
        fputcsv($output, ['Deductions Summary Report']);
        fputcsv($output, []);
        fputcsv($output, ['Statutory Deductions', number_format($report['total_statutory_deductions'] ?? 0, 2)]);
        fputcsv($output, ['Custom Deductions', number_format($report['total_custom_deductions'] ?? 0, 2)]);
        fputcsv($output, ['Total All Deductions', number_format($report['total_all_deductions'] ?? 0, 2)]);
        fputcsv($output, []);

        if (isset($report['deductions']) && count($report['deductions']) > 0) {
            fputcsv($output, ['Deduction Name', 'Type', 'Total Amount', 'Count']);
            foreach ($report['deductions'] as $deduction) {
                fputcsv($output, [
                    $deduction['name'] ?? 'Unknown',
                    $deduction['type'] ?? 'fixed',
                    number_format($deduction['total_amount'] ?? 0, 2),
                    $deduction['count'] ?? 0,
                ]);
            }
        }
    }

    private function writePaymentSummaryCsv($output, array $report): void
    {
        fputcsv($output, ['Payment Summary Report']);
        fputcsv($output, []);
        fputcsv($output, ['Total Jobs', $report['total_jobs'] ?? 0]);
        fputcsv($output, ['Total Amount', number_format($report['total_amount'] ?? 0, 2)]);
        fputcsv($output, ['Total Fees', number_format($report['total_fees'] ?? 0, 2)]);
        fputcsv($output, []);

        if (isset($report['jobs']) && count($report['jobs']) > 0) {
            fputcsv($output, ['Receiver', 'Amount', 'Fee', 'Currency', 'Processed At']);
            foreach ($report['jobs'] as $job) {
                fputcsv($output, [
                    $job['receiver_name'] ?? 'N/A',
                    number_format($job['amount'] ?? 0, 2),
                    number_format($job['fee'] ?? 0, 2),
                    $job['currency'] ?? 'ZAR',
                    $job['processed_at'] ?? 'N/A',
                ]);
            }
        }
    }

    private function writeEmployeeEarningsCsv($output, array $report): void
    {
        fputcsv($output, ['Employee Earnings Report']);
        fputcsv($output, []);

        if (isset($report['employees']) && count($report['employees']) > 0) {
            fputcsv($output, ['Employee', 'Email', 'Total Payments', 'Total Gross', 'Average Gross', 'Total Net', 'Average Net', 'Total Deductions', 'Deduction %']);
            foreach ($report['employees'] as $emp) {
                fputcsv($output, [
                    $emp['employee_name'] ?? 'N/A',
                    $emp['employee_email'] ?? '',
                    $emp['total_payments'] ?? 0,
                    number_format($emp['total_gross'] ?? 0, 2),
                    number_format($emp['average_gross'] ?? 0, 2),
                    number_format($emp['total_net'] ?? 0, 2),
                    number_format($emp['average_net'] ?? 0, 2),
                    number_format($emp['total_deductions'] ?? 0, 2),
                    number_format($emp['deduction_percentage'] ?? 0, 2).'%',
                ]);
            }
        }
    }
}
