<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateReportJob;
use App\Models\Employee;
use App\Models\PaymentJob;
use App\Models\PayrollJob;
use App\Models\ReportGeneration;
use App\Services\SseConnectionTracker;
use Barryvdh\DomPDF\Facade\Pdf as PDF;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    /**
     * Get report data (on-demand endpoint for progressive loading)
     */
    public function fetchReportData(Request $request): \Illuminate\Http\JsonResponse
    {
        // Get business_id from request, or use current user's active business
        $requestBusinessId = $request->get('business_id');
        $businessId = null;

        // If business_id is provided and not empty, use it
        if ($requestBusinessId && $requestBusinessId !== '' && $requestBusinessId !== 'all') {
            $businessId = (string) $requestBusinessId;
        } else {
            // Otherwise use current user's active business
            $user = Auth::user();
            $businessId = $user->current_business_id
                ? (string) $user->current_business_id
                : (session('current_business_id') ? (string) session('current_business_id') : null);
        }

        $reportType = $request->get('report_type', 'payroll_summary');
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');

        Log::debug('Fetching report data', [
            'report_type' => $reportType,
            'business_id' => $businessId,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'user_id' => Auth::id(),
        ]);

        // Generate report data based on type
        $report = match ($reportType) {
            'payroll_summary' => $this->getPayrollSummary($businessId, $startDate, $endDate),
            'payroll_by_employee' => $this->getPayrollByEmployee($businessId, $startDate, $endDate),
            'tax_summary' => $this->getTaxSummary($businessId, $startDate, $endDate),
            'deductions_summary' => $this->getDeductionsSummary($businessId, $startDate, $endDate),
            'payment_summary' => $this->getPaymentSummary($businessId, $startDate, $endDate),
            'employee_earnings' => $this->getEmployeeEarnings($businessId, $startDate, $endDate),
            default => $this->getPayrollSummary($businessId, $startDate, $endDate),
        };

        return response()->json($report);
    }

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
            'business_id' => $businessId ? (string) $businessId : null, // Convert to string for frontend consistency
            'start_date' => $startDate,
            'end_date' => $endDate,
            'businesses' => $businesses,
            // Report data is now loaded on-demand via fetchReportData endpoint
            // This prevents blocking the initial page load
            'report' => null,
        ];

        return Inertia::render('reports/index', $data);
    }

    /**
     * Get payroll summary report
     * Optimized to use SQL aggregations with JOINs
     */
    private function getPayrollSummary(?string $businessId, ?string $startDate, ?string $endDate, ?int $userId = null): array
    {
        $userId = $userId ?? Auth::id();
        $query = PayrollJob::query()
            ->join('payroll_schedules', 'payroll_jobs.payroll_schedule_id', '=', 'payroll_schedules.id')
            ->where('payroll_jobs.status', 'succeeded');

        if ($businessId) {
            $query->where('payroll_schedules.business_id', $businessId);
        } else {
            $user = \App\Models\User::find($userId);
            $userBusinessIds = $user->businesses()->pluck('businesses.id')->toArray();
            $query->whereIn('payroll_schedules.business_id', $userBusinessIds);
        }

        if ($startDate) {
            $query->whereDate('payroll_jobs.pay_period_start', '>=', $startDate);
        }
        if ($endDate) {
            $query->whereDate('payroll_jobs.pay_period_end', '<=', $endDate);
        }

        // Get aggregated summary using SQL
        $summary = $query->select([
            DB::raw('COUNT(*) as total_jobs'),
            DB::raw('COALESCE(SUM(payroll_jobs.gross_salary), 0) as total_gross'),
            DB::raw('COALESCE(SUM(payroll_jobs.paye_amount), 0) as total_paye'),
            DB::raw('COALESCE(SUM(payroll_jobs.uif_amount), 0) as total_uif'),
            DB::raw('COALESCE(SUM(payroll_jobs.sdl_amount), 0) as total_sdl'),
            DB::raw('COALESCE(SUM(payroll_jobs.net_salary), 0) as total_net'),
        ])->first();

        // Get detailed jobs list with eager loading
        $jobs = (clone $query)
            ->with(['employee:id,name', 'payrollSchedule:id,business_id'])
            ->select([
                'payroll_jobs.id',
                'payroll_jobs.employee_id',
                'payroll_jobs.payroll_schedule_id',
                'payroll_jobs.gross_salary',
                'payroll_jobs.paye_amount',
                'payroll_jobs.uif_amount',
                'payroll_jobs.sdl_amount',
                'payroll_jobs.adjustments',
                'payroll_jobs.net_salary',
                'payroll_jobs.pay_period_start',
                'payroll_jobs.pay_period_end',
                'payroll_jobs.processed_at',
            ])
            ->get();

        // Calculate total adjustments (deductions only, not additions)
        $totalAdjustments = $jobs->sum(function ($job) {
            return collect($job->adjustments ?? [])
                ->filter(fn ($adj) => ($adj['adjustment_type'] ?? 'deduction') === 'deduction')
                ->sum('amount');
        });

        return [
            'total_jobs' => $summary->total_jobs ?? 0,
            'total_gross' => (float) ($summary->total_gross ?? 0),
            'total_paye' => (float) ($summary->total_paye ?? 0),
            'total_uif' => (float) ($summary->total_uif ?? 0),
            'total_sdl' => (float) ($summary->total_sdl ?? 0),
            'total_adjustments' => $totalAdjustments,
            'total_net' => (float) ($summary->total_net ?? 0),
            'jobs' => $jobs->map(function ($job) {
                return [
                    'id' => $job->id,
                    'employee_name' => $job->employee->name ?? 'N/A',
                    'gross_salary' => $job->gross_salary,
                    'paye_amount' => $job->paye_amount,
                    'uif_amount' => $job->uif_amount,
                    'sdl_amount' => $job->sdl_amount,
                    'adjustments' => $job->adjustments ?? [],
                    'adjustments_total' => collect($job->adjustments ?? [])
                        ->filter(fn ($adj) => ($adj['adjustment_type'] ?? 'deduction') === 'deduction')
                        ->sum('amount'),
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
     * Optimized to use SQL GROUP BY with JOINs
     */
    private function getPayrollByEmployee(?string $businessId, ?string $startDate, ?string $endDate, ?int $userId = null): array
    {
        $userId = $userId ?? Auth::id();
        $query = PayrollJob::query()
            ->where('payroll_jobs.status', 'succeeded')
            ->join('payroll_schedules', 'payroll_jobs.payroll_schedule_id', '=', 'payroll_schedules.id')
            ->join('employees', 'payroll_jobs.employee_id', '=', 'employees.id');

        if ($businessId) {
            $query->where('payroll_schedules.business_id', $businessId);
        } else {
            $user = \App\Models\User::find($userId);
            $userBusinessIds = $user->businesses()->pluck('businesses.id')->toArray();
            $query->whereIn('payroll_schedules.business_id', $userBusinessIds);
        }

        if ($startDate) {
            $query->whereDate('payroll_jobs.pay_period_start', '>=', $startDate);
        }
        if ($endDate) {
            $query->whereDate('payroll_jobs.pay_period_end', '<=', $endDate);
        }

        // Use SQL GROUP BY for aggregation
        $byEmployee = $query->select([
            'payroll_jobs.employee_id',
            'employees.name as employee_name',
            DB::raw('COUNT(*) as total_jobs'),
            DB::raw('COALESCE(SUM(payroll_jobs.gross_salary), 0) as total_gross'),
            DB::raw('COALESCE(SUM(payroll_jobs.paye_amount), 0) as total_paye'),
            DB::raw('COALESCE(SUM(payroll_jobs.uif_amount), 0) as total_uif'),
            DB::raw('COALESCE(SUM(payroll_jobs.sdl_amount), 0) as total_sdl'),
            DB::raw('COALESCE(SUM(payroll_jobs.net_salary), 0) as total_net'),
        ])
            ->groupBy('payroll_jobs.employee_id', 'employees.name')
            ->get();

        // Get detailed jobs for each employee
        $employeeIds = $byEmployee->pluck('employee_id');
        $detailedJobs = PayrollJob::query()
            ->where('status', 'succeeded')
            ->whereIn('employee_id', $employeeIds)
            ->select(['id', 'employee_id', 'gross_salary', 'net_salary', 'pay_period_start', 'pay_period_end', 'adjustments'])
            ->get()
            ->groupBy('employee_id');

        // Calculate adjustments per employee (deductions only)
        $adjustmentsByEmployee = [];
        foreach ($detailedJobs as $employeeId => $jobs) {
            $adjustmentsByEmployee[$employeeId] = $jobs->sum(function ($job) {
                return collect($job->adjustments ?? [])
                    ->filter(fn ($adj) => ($adj['adjustment_type'] ?? 'deduction') === 'deduction')
                    ->sum('amount');
            });
        }

        $employees = $byEmployee->map(function ($emp) use ($detailedJobs, $adjustmentsByEmployee) {
            $jobs = $detailedJobs->get($emp->employee_id, collect());

            return [
                'employee_id' => $emp->employee_id,
                'employee_name' => $emp->employee_name ?? 'N/A',
                'total_jobs' => $emp->total_jobs,
                'total_gross' => (float) $emp->total_gross,
                'total_paye' => (float) $emp->total_paye,
                'total_uif' => (float) $emp->total_uif,
                'total_sdl' => (float) $emp->total_sdl,
                'total_adjustments' => $adjustmentsByEmployee[$emp->employee_id] ?? 0,
                'total_net' => (float) $emp->total_net,
                'jobs' => $jobs->map(function ($job) {
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
            'employees' => $employees,
            'total_employees' => $employees->count(),
            'summary' => [
                'total_gross' => $employees->sum('total_gross'),
                'total_net' => $employees->sum('total_net'),
                'total_paye' => $employees->sum('total_paye'),
                'total_uif' => $employees->sum('total_uif'),
                'total_sdl' => $employees->sum('total_sdl'),
            ],
        ];
    }

    /**
     * Get tax summary report
     * Optimized to use SQL aggregations with JOINs
     */
    private function getTaxSummary(?string $businessId, ?string $startDate, ?string $endDate, ?int $userId = null): array
    {
        $userId = $userId ?? Auth::id();
        $query = PayrollJob::query()
            ->join('payroll_schedules', 'payroll_jobs.payroll_schedule_id', '=', 'payroll_schedules.id')
            ->where('payroll_jobs.status', 'succeeded');

        if ($businessId) {
            $query->where('payroll_schedules.business_id', $businessId);
        } else {
            $user = \App\Models\User::find($userId);
            $userBusinessIds = $user->businesses()->pluck('businesses.id')->toArray();
            $query->whereIn('payroll_schedules.business_id', $userBusinessIds);
        }

        if ($startDate) {
            $query->whereDate('payroll_jobs.pay_period_start', '>=', $startDate);
        }
        if ($endDate) {
            $query->whereDate('payroll_jobs.pay_period_end', '<=', $endDate);
        }

        // Use SQL aggregations for all tax calculations
        $summary = $query->select([
            DB::raw('COALESCE(SUM(payroll_jobs.paye_amount), 0) as total_paye'),
            DB::raw('COALESCE(SUM(payroll_jobs.uif_amount), 0) as total_uif'),
            DB::raw('COALESCE(SUM(payroll_jobs.sdl_amount), 0) as total_sdl'),
            DB::raw('COUNT(CASE WHEN payroll_jobs.paye_amount > 0 THEN 1 END) as paye_count'),
            DB::raw('COUNT(CASE WHEN payroll_jobs.uif_amount > 0 THEN 1 END) as uif_count'),
            DB::raw('COUNT(CASE WHEN payroll_jobs.sdl_amount > 0 THEN 1 END) as sdl_count'),
            DB::raw('AVG(CASE WHEN payroll_jobs.paye_amount > 0 THEN payroll_jobs.paye_amount END) as paye_avg'),
            DB::raw('AVG(CASE WHEN payroll_jobs.uif_amount > 0 THEN payroll_jobs.uif_amount END) as uif_avg'),
            DB::raw('AVG(CASE WHEN payroll_jobs.sdl_amount > 0 THEN payroll_jobs.sdl_amount END) as sdl_avg'),
        ])->first();

        return [
            'paye' => [
                'total' => (float) ($summary->total_paye ?? 0),
                'count' => $summary->paye_count ?? 0,
                'average' => (float) ($summary->paye_avg ?? 0),
            ],
            'uif' => [
                'total' => (float) ($summary->total_uif ?? 0),
                'count' => $summary->uif_count ?? 0,
                'average' => (float) ($summary->uif_avg ?? 0),
            ],
            'sdl' => [
                'total' => (float) ($summary->total_sdl ?? 0),
                'count' => $summary->sdl_count ?? 0,
                'average' => (float) ($summary->sdl_avg ?? 0),
            ],
            'total_tax_liability' => (float) (($summary->total_paye ?? 0) + ($summary->total_uif ?? 0)),
            'total_employer_costs' => (float) ($summary->total_sdl ?? 0),
        ];
    }

    /**
     * Get deductions summary report
     */
    private function getDeductionsSummary(?string $businessId, ?string $startDate, ?string $endDate, ?int $userId = null): array
    {
        $userId = $userId ?? Auth::id();
        $query = PayrollJob::query()
            ->select(['payroll_jobs.*'])
            ->join('payroll_schedules', 'payroll_jobs.payroll_schedule_id', '=', 'payroll_schedules.id')
            ->where('payroll_jobs.status', 'succeeded')
            ->with(['employee', 'payrollSchedule.business']);

        if ($businessId) {
            $query->where('payroll_schedules.business_id', $businessId);
        } else {
            $user = \App\Models\User::find($userId);
            $userBusinessIds = $user->businesses()->pluck('businesses.id')->toArray();
            $query->whereIn('payroll_schedules.business_id', $userBusinessIds);
        }

        if ($startDate) {
            $query->whereDate('payroll_jobs.pay_period_start', '>=', $startDate);
        }
        if ($endDate) {
            $query->whereDate('pay_period_end', '<=', $endDate);
        }

        $jobs = $query->get();

        // Aggregate adjustments by name (deductions only)
        $adjustmentBreakdown = [];
        foreach ($jobs as $job) {
            foreach ($job->adjustments ?? [] as $adjustment) {
                // Only include deduction-type adjustments
                if (($adjustment['adjustment_type'] ?? 'deduction') === 'deduction') {
                    $name = $adjustment['name'] ?? 'Unknown';
                    if (! isset($adjustmentBreakdown[$name])) {
                        $adjustmentBreakdown[$name] = [
                            'name' => $name,
                            'type' => $adjustment['type'] ?? 'fixed',
                            'total_amount' => 0,
                            'count' => 0,
                        ];
                    }
                    $adjustmentBreakdown[$name]['total_amount'] += $adjustment['amount'] ?? 0;
                    $adjustmentBreakdown[$name]['count']++;
                }
            }
        }

        $totalAdjustments = $jobs->sum(function ($job) {
            return collect($job->adjustments ?? [])
                ->filter(fn ($adj) => ($adj['adjustment_type'] ?? 'deduction') === 'deduction')
                ->sum('amount');
        });

        return [
            'deductions' => array_values($adjustmentBreakdown),
            'total_adjustments' => $totalAdjustments,
            'total_statutory_deductions' => $jobs->sum('paye_amount') + $jobs->sum('uif_amount'),
            'total_all_deductions' => $jobs->sum(function ($job) {
                $adjustmentTotal = collect($job->adjustments ?? [])
                    ->filter(fn ($adj) => ($adj['adjustment_type'] ?? 'deduction') === 'deduction')
                    ->sum('amount');

                return $job->paye_amount + $job->uif_amount + $adjustmentTotal;
            }),
        ];
    }

    /**
     * Get payment summary report
     * Optimized to use SQL aggregations with JOINs
     */
    private function getPaymentSummary(?string $businessId, ?string $startDate, ?string $endDate, ?int $userId = null): array
    {
        $userId = $userId ?? Auth::id();
        $query = PaymentJob::query()
            ->join('payment_schedules', 'payment_jobs.payment_schedule_id', '=', 'payment_schedules.id')
            ->where('payment_jobs.status', 'succeeded');

        if ($businessId) {
            $query->where('payment_schedules.business_id', $businessId);
        } else {
            $user = \App\Models\User::find($userId);
            $userBusinessIds = $user->businesses()->pluck('businesses.id')->toArray();
            $query->whereIn('payment_schedules.business_id', $userBusinessIds);
        }

        if ($startDate) {
            $query->whereDate('payment_jobs.processed_at', '>=', $startDate);
        }
        if ($endDate) {
            $query->whereDate('payment_jobs.processed_at', '<=', $endDate);
        }

        // Get aggregated summary using SQL
        $summary = $query->select([
            DB::raw('COUNT(*) as total_jobs'),
            DB::raw('COALESCE(SUM(payment_jobs.amount), 0) as total_amount'),
            DB::raw('COALESCE(SUM(payment_jobs.fee), 0) as total_fees'),
        ])->first();

        // Get detailed jobs list with eager loading
        $jobs = (clone $query)
            ->with(['recipient:id,name'])
            ->select(['payment_jobs.id', 'payment_jobs.recipient_id', 'payment_jobs.amount', 'payment_jobs.fee', 'payment_jobs.currency', 'payment_jobs.processed_at'])
            ->get();

        return [
            'total_jobs' => $summary->total_jobs ?? 0,
            'total_amount' => (float) ($summary->total_amount ?? 0),
            'total_fees' => (float) ($summary->total_fees ?? 0),
            'jobs' => $jobs->map(function ($job) {
                return [
                    'id' => $job->id,
                    'receiver_name' => $job->recipient?->name ?? 'N/A',
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
     * Optimized to use SQL GROUP BY with JOINs
     */
    private function getEmployeeEarnings(?string $businessId, ?string $startDate, ?string $endDate, ?int $userId = null): array
    {
        $userId = $userId ?? Auth::id();
        $query = PayrollJob::query()
            ->where('payroll_jobs.status', 'succeeded')
            ->join('payroll_schedules', 'payroll_jobs.payroll_schedule_id', '=', 'payroll_schedules.id')
            ->join('employees', 'payroll_jobs.employee_id', '=', 'employees.id');

        if ($businessId) {
            $query->where('payroll_schedules.business_id', $businessId);
        } else {
            $user = \App\Models\User::find($userId);
            $userBusinessIds = $user->businesses()->pluck('businesses.id')->toArray();
            $query->whereIn('payroll_schedules.business_id', $userBusinessIds);
        }

        if ($startDate) {
            $query->whereDate('payroll_jobs.pay_period_start', '>=', $startDate);
        }
        if ($endDate) {
            $query->whereDate('payroll_jobs.pay_period_end', '<=', $endDate);
        }

        // Use SQL GROUP BY for aggregation
        $byEmployee = $query->select([
            'payroll_jobs.employee_id',
            'employees.name as employee_name',
            'employees.email as employee_email',
            DB::raw('COUNT(*) as total_payments'),
            DB::raw('COALESCE(SUM(payroll_jobs.gross_salary), 0) as total_gross'),
            DB::raw('COALESCE(SUM(payroll_jobs.net_salary), 0) as total_net'),
            DB::raw('COALESCE(AVG(payroll_jobs.gross_salary), 0) as average_gross'),
            DB::raw('COALESCE(AVG(payroll_jobs.net_salary), 0) as average_net'),
        ])
            ->groupBy('payroll_jobs.employee_id', 'employees.name', 'employees.email')
            ->get()
            ->map(function ($emp) {
                $totalGross = (float) $emp->total_gross;
                $totalNet = (float) $emp->total_net;
                $totalDeductions = $totalGross - $totalNet;

                return [
                    'employee_id' => $emp->employee_id,
                    'employee_name' => $emp->employee_name ?? 'N/A',
                    'employee_email' => $emp->employee_email,
                    'total_payments' => $emp->total_payments,
                    'total_gross' => $totalGross,
                    'total_net' => $totalNet,
                    'average_gross' => (float) $emp->average_gross,
                    'average_net' => (float) $emp->average_net,
                    'total_deductions' => $totalDeductions,
                    'deduction_percentage' => $totalGross > 0 ? ($totalDeductions / $totalGross) * 100 : 0,
                ];
            })
            ->values();

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
     * Supports two delivery methods (both queued):
     * - email: Queued generation with email notification (default)
     * - download: Queued generation, returns ID for polling and download
     */
    public function exportCsv(Request $request)
    {
        $delivery = $request->get('delivery', 'email'); // 'email' or 'download'
        $user = Auth::user();
        $businessId = $request->get('business_id') ?? $user->current_business_id ?? session('current_business_id');
        $reportType = $request->get('report_type', 'payroll_summary');
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');

        // Create report generation record
        $reportGeneration = ReportGeneration::create([
            'user_id' => $user->id,
            'business_id' => $businessId,
            'report_type' => $reportType,
            'format' => 'csv',
            'status' => 'pending',
            'delivery_method' => $delivery,
            'parameters' => [
                'business_id' => $businessId,
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
        ]);

        // Queue the job (both email and download are queued)
        GenerateReportJob::dispatch($reportGeneration)->onQueue('reports');

        // If AJAX request (but NOT Inertia request), return JSON instead of redirecting
        // Check for AJAX request and absence of X-Inertia headers
        $isInertiaRequest = $request->header('X-Inertia') || $request->header('X-Inertia-Version');
        if ($request->ajax() && ! $isInertiaRequest) {
            return response()->json([
                'success' => true,
                'report_generation_id' => $reportGeneration->id,
                'delivery_method' => $delivery,
                'sse_url' => route('reports.stream', $reportGeneration),
                'download_url' => route('reports.download', $reportGeneration),
            ]);
        }

        // For non-AJAX requests, redirect (fallback for direct browser navigation)
        // For direct download, redirect to download page with SSE
        if ($delivery === 'download') {
            return redirect()->route('reports.download-wait', $reportGeneration);
        }

        // Email delivery - redirect to email wait page
        return redirect()->route('reports.email-wait', $reportGeneration);
    }

    /**
     * Export report to PDF
     * Supports two delivery methods (both queued):
     * - email: Queued generation with email notification (default)
     * - download: Queued generation, returns ID for polling and download
     */
    public function exportPdf(Request $request)
    {
        $delivery = $request->get('delivery', 'email'); // 'email' or 'download'
        $user = Auth::user();
        $businessId = $request->get('business_id') ?? $user->current_business_id ?? session('current_business_id');
        $reportType = $request->get('report_type', 'payroll_summary');
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');

        // Create report generation record
        $reportGeneration = ReportGeneration::create([
            'user_id' => $user->id,
            'business_id' => $businessId,
            'report_type' => $reportType,
            'format' => 'pdf',
            'status' => 'pending',
            'delivery_method' => $delivery,
            'parameters' => [
                'business_id' => $businessId,
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
        ]);

        // Queue the job (both email and download are queued)
        GenerateReportJob::dispatch($reportGeneration)->onQueue('reports');

        // If AJAX request (but NOT Inertia request), return JSON instead of redirecting
        // Check for AJAX request and absence of X-Inertia headers
        $isInertiaRequest = $request->header('X-Inertia') || $request->header('X-Inertia-Version');
        if ($request->ajax() && ! $isInertiaRequest) {
            return response()->json([
                'success' => true,
                'report_generation_id' => $reportGeneration->id,
                'delivery_method' => $delivery,
                'sse_url' => route('reports.stream', $reportGeneration),
                'download_url' => route('reports.download', $reportGeneration),
            ]);
        }

        // For non-AJAX requests, redirect (fallback for direct browser navigation)
        // For direct download, redirect to download page with SSE
        if ($delivery === 'download') {
            return redirect()->route('reports.download-wait', $reportGeneration);
        }

        // Email delivery - redirect to email wait page
        return redirect()->route('reports.email-wait', $reportGeneration);
    }

    /**
     * Export report to Excel (CSV format)
     * Supports two delivery methods (both queued):
     * - email: Queued generation with email notification (default)
     * - download: Queued generation, redirects to download wait page with SSE
     */
    public function exportExcel(Request $request)
    {
        $delivery = $request->get('delivery', 'email'); // 'email' or 'download'
        $user = Auth::user();
        $businessId = $request->get('business_id') ?? $user->current_business_id ?? session('current_business_id');
        $reportType = $request->get('report_type', 'payroll_summary');
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');

        // Create report generation record
        $reportGeneration = ReportGeneration::create([
            'user_id' => $user->id,
            'business_id' => $businessId,
            'report_type' => $reportType,
            'format' => 'csv', // Excel uses CSV format
            'status' => 'pending',
            'delivery_method' => $delivery,
            'parameters' => [
                'business_id' => $businessId,
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
        ]);

        // Queue the job (both email and download are queued)
        GenerateReportJob::dispatch($reportGeneration)->onQueue('reports');

        // If AJAX request (but NOT Inertia request), return JSON instead of redirecting
        // Check for AJAX request and absence of X-Inertia headers
        $isInertiaRequest = $request->header('X-Inertia') || $request->header('X-Inertia-Version');
        if ($request->ajax() && ! $isInertiaRequest) {
            return response()->json([
                'success' => true,
                'report_generation_id' => $reportGeneration->id,
                'delivery_method' => $delivery,
                'sse_url' => route('reports.stream', $reportGeneration),
                'download_url' => route('reports.download', $reportGeneration),
            ]);
        }

        // For direct download, redirect to download wait page with SSE
        if ($delivery === 'download') {
            return redirect()->route('reports.download-wait', $reportGeneration);
        }

        // Email delivery - redirect to email wait page
        return redirect()->route('reports.email-wait', $reportGeneration);
    }

    /**
     * Show email wait page (for email delivery)
     */
    public function emailWait(ReportGeneration $reportGeneration): Response
    {
        // Verify user owns this report
        if ($reportGeneration->user_id !== Auth::id()) {
            abort(403, 'You do not have permission to access this report.');
        }

        return Inertia::render('reports/email-wait', [
            'reportGeneration' => [
                'id' => $reportGeneration->id,
                'status' => $reportGeneration->status,
                'report_type' => $reportGeneration->report_type,
                'format' => $reportGeneration->format,
                'sse_url' => route('reports.stream', $reportGeneration),
            ],
        ]);
    }

    /**
     * Show download wait page (for direct download)
     */
    public function downloadWait(ReportGeneration $reportGeneration): Response
    {
        // Verify user owns this report
        if ($reportGeneration->user_id !== Auth::id()) {
            abort(403, 'You do not have permission to access this report.');
        }

        return Inertia::render('reports/download-wait', [
            'reportGeneration' => [
                'id' => $reportGeneration->id,
                'status' => $reportGeneration->status,
                'report_type' => $reportGeneration->report_type,
                'format' => $reportGeneration->format,
                'download_url' => route('reports.download', $reportGeneration),
                'sse_url' => route('reports.stream', $reportGeneration),
            ],
        ]);
    }

    /**
     * Stream report status via Server-Sent Events (SSE)
     * Includes connection monitoring and client disconnect detection
     */
    public function stream(ReportGeneration $reportGeneration)
    {
        // Verify user owns this report
        if ($reportGeneration->user_id !== Auth::id()) {
            abort(403, 'You do not have permission to access this report.');
        }

        // Track connection for monitoring
        $connectionTracker = app(SseConnectionTracker::class);
        $userId = Auth::id();
        $connectionTracker->track($reportGeneration->id, $userId);

        // Log connection for monitoring
        Log::info('SSE connection opened', [
            'report_generation_id' => $reportGeneration->id,
            'user_id' => $userId,
        ]);

        return response()->stream(function () use ($reportGeneration, $connectionTracker, $userId) {
            // Set time limit for SSE connection
            set_time_limit(300); // 5 minutes max
            ignore_user_abort(false); // Detect client disconnects

            $maxAttempts = 300; // 5 minutes max (300 * 1 second)
            $attempt = 0;
            $heartbeatInterval = 30; // Send explicit heartbeat every 30 seconds
            $lastHeartbeat = time();

            // Send initial connection confirmation
            echo "event: connected\n";
            echo 'data: '.json_encode(['status' => $reportGeneration->status, 'message' => 'Connected'])."\n\n";
            flush();

            while ($attempt < $maxAttempts) {
                // Check if client disconnected
                if (connection_aborted()) {
                    // Untrack connection on client disconnect
                    $connectionTracker->untrack($reportGeneration->id, $userId);

                    Log::info('SSE connection closed by client', [
                        'report_generation_id' => $reportGeneration->id,
                        'user_id' => $userId,
                        'attempt' => $attempt,
                    ]);
                    break;
                }

                // Get fresh instance with consistent read (no lock needed for read-only)
                $reportGeneration->refresh();

                $data = [
                    'status' => $reportGeneration->status,
                    'filename' => $reportGeneration->filename,
                    'timestamp' => now()->toIso8601String(),
                ];

                // Check for terminal states
                if ($reportGeneration->status === 'completed') {
                    $data['download_url'] = route('reports.download', $reportGeneration);
                    echo 'data: '.json_encode($data)."\n\n";
                    flush();

                    // Untrack connection on completion
                    $connectionTracker->untrack($reportGeneration->id, $userId);

                    Log::info('SSE connection completed successfully', [
                        'report_generation_id' => $reportGeneration->id,
                        'user_id' => $userId,
                    ]);
                    break;
                }

                if ($reportGeneration->status === 'failed') {
                    $data['error_message'] = $reportGeneration->error_message;
                    echo 'data: '.json_encode($data)."\n\n";
                    flush();

                    // Untrack connection on failure
                    $connectionTracker->untrack($reportGeneration->id, $userId);

                    Log::info('SSE connection ended - report failed', [
                        'report_generation_id' => $reportGeneration->id,
                        'user_id' => $userId,
                        'error' => $reportGeneration->error_message,
                    ]);
                    break;
                }

                // Send explicit heartbeat every heartbeatInterval seconds
                $currentTime = time();
                if ($currentTime - $lastHeartbeat >= $heartbeatInterval) {
                    echo "event: heartbeat\n";
                    echo 'data: '.json_encode(['timestamp' => now()->toIso8601String()])."\n\n";
                    flush();
                    $lastHeartbeat = $currentTime;
                }

                // Send status update
                echo 'data: '.json_encode($data)."\n\n";
                flush();

                // Sleep with connection check
                $sleepTime = 1;
                $elapsed = 0;
                while ($elapsed < $sleepTime) {
                    if (connection_aborted()) {
                        // Untrack connection on client disconnect
                        $connectionTracker->untrack($reportGeneration->id, $userId);
                        break 2; // Break out of both loops
                    }
                    usleep(100000); // 0.1 second increments
                    $elapsed += 0.1;
                }

                $attempt++;
            }

            // Handle timeout
            if ($attempt >= $maxAttempts) {
                echo 'data: '.json_encode([
                    'status' => 'timeout',
                    'message' => 'Report generation timed out. Please try again.',
                    'timestamp' => now()->toIso8601String(),
                ])."\n\n";
                flush();

                // Untrack connection on timeout
                $connectionTracker->untrack($reportGeneration->id, $userId);

                Log::warning('SSE connection timed out', [
                    'report_generation_id' => $reportGeneration->id,
                    'user_id' => $userId,
                    'attempts' => $attempt,
                ]);
            }

            // Send close event
            echo "event: close\n";
            echo 'data: '.json_encode(['message' => 'Connection closed'])."\n\n";
            flush();

            // Untrack connection
            $connectionTracker->untrack($reportGeneration->id, $userId);

            Log::info('SSE connection closed', [
                'report_generation_id' => $reportGeneration->id,
                'user_id' => $userId,
                'final_status' => $reportGeneration->status,
            ]);
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
            'X-Accel-Buffering' => 'no', // Disable nginx buffering
            'Connection' => 'keep-alive',
        ]);
    }

    /**
     * Download a generated report
     */
    public function download(ReportGeneration $reportGeneration)
    {
        // Verify user owns this report
        if ($reportGeneration->user_id !== Auth::id()) {
            abort(403, 'You do not have permission to download this report.');
        }

        // Verify report is completed
        if ($reportGeneration->status !== 'completed') {
            return redirect()->back()
                ->with('error', 'This report is not ready yet. Please wait for the email notification.');
        }

        // Verify file exists
        if (! Storage::disk('local')->exists($reportGeneration->file_path)) {
            return redirect()->back()
                ->with('error', 'Report file not found. Please regenerate the report.');
        }

        $mimeType = $reportGeneration->format === 'pdf' ? 'application/pdf' : 'text/csv; charset=UTF-8';

        return Storage::disk('local')->download(
            $reportGeneration->file_path,
            $reportGeneration->filename,
            [
                'Content-Type' => $mimeType,
            ]
        );
    }

    /**
     * Get report data based on type
     * Made public so GenerateReportJob can access it
     */
    public function getReportData(string $reportType, ?string $businessId, ?string $startDate, ?string $endDate, ?int $userId = null): array
    {
        $userId = $userId ?? Auth::id();

        switch ($reportType) {
            case 'payroll_summary':
                return $this->getPayrollSummary($businessId, $startDate, $endDate, $userId);
            case 'payroll_by_employee':
                return $this->getPayrollByEmployee($businessId, $startDate, $endDate, $userId);
            case 'tax_summary':
                return $this->getTaxSummary($businessId, $startDate, $endDate, $userId);
            case 'deductions_summary':
                return $this->getDeductionsSummary($businessId, $startDate, $endDate, $userId);
            case 'payment_summary':
                return $this->getPaymentSummary($businessId, $startDate, $endDate, $userId);
            case 'employee_earnings':
                return $this->getEmployeeEarnings($businessId, $startDate, $endDate, $userId);
            default:
                return $this->getPayrollSummary($businessId, $startDate, $endDate, $userId);
        }
    }

    /**
     * Get export filename
     * Made public so direct export methods can access it
     */
    public function getExportFilename(string $reportType, ?string $startDate, ?string $endDate, string $extension): string
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
     * Made public so GenerateReportJob can access it
     */
    public function writeCsvData($output, array $report, string $reportType): void
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
        fputcsv($output, ['Total Adjustments', number_format($report['total_adjustments'] ?? 0, 2)]);
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
                    number_format($job['adjustments_total'] ?? 0, 2),
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
                    number_format($emp['total_adjustments'] ?? 0, 2),
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
        fputcsv($output, ['Adjustments', number_format($report['total_adjustments'] ?? 0, 2)]);
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
