<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\ComplianceSubmission;
use App\Models\Employee;
use App\Models\PaymentJob;
use App\Models\PaymentSchedule;
use App\Models\PayrollJob;
use App\Models\PayrollSchedule;
use App\Services\EscrowService;
use Illuminate\Http\JsonResponse;

class AiDataController extends Controller
{
    public function __construct(
        protected EscrowService $escrowService
    ) {}

    /**
     * Get business summary (safe, non-sensitive data).
     */
    public function businessSummary(Business $business): JsonResponse
    {
        return response()->json([
            'id' => $business->id,
            'name' => $business->name,
            'business_type' => $business->business_type,
            'status' => $business->status,
            'city' => $business->city,
            'province' => $business->province,
            'country' => $business->country,
            'created_at' => $business->created_at->toDateString(),
        ]);
    }

    /**
     * Get employees summary (aggregated, no sensitive data).
     */
    public function employeesSummary(Business $business): JsonResponse
    {
        $employees = Employee::where('business_id', $business->id)->get();

        $departmentCounts = $employees->groupBy('department')
            ->map(fn ($group) => $group->count())
            ->toArray();

        $employmentTypeCounts = $employees->groupBy('employment_type')
            ->map(fn ($group) => $group->count())
            ->toArray();

        return response()->json([
            'total_count' => $employees->count(),
            'departments' => $departmentCounts,
            'employment_types' => $employmentTypeCounts,
            'average_salary' => $employees->avg('gross_salary') ? round($employees->avg('gross_salary'), 2) : 0,
            'total_monthly_payroll' => round($employees->sum('gross_salary'), 2),
        ]);
    }

    /**
     * Get payments summary (aggregated).
     */
    public function paymentsSummary(Business $business): JsonResponse
    {
        $schedules = PaymentSchedule::where('business_id', $business->id)->get();

        $statusCounts = $schedules->groupBy('status')
            ->map(fn ($group) => $group->count())
            ->toArray();

        $upcomingSchedules = $schedules
            ->where('status', 'active')
            ->where('next_run_at', '>=', now())
            ->sortBy('next_run_at')
            ->take(5)
            ->map(fn ($schedule) => [
                'name' => $schedule->name,
                'amount' => $schedule->amount,
                'currency' => $schedule->currency,
                'next_run_at' => $schedule->next_run_at?->toDateTimeString(),
                'frequency' => $schedule->frequency,
            ])
            ->values()
            ->toArray();

        // Get recent payment jobs stats using JOIN instead of whereHas
        $recentJobs = PaymentJob::query()
            ->select(['payment_jobs.*'])
            ->join('payment_schedules', 'payment_jobs.payment_schedule_id', '=', 'payment_schedules.id')
            ->where('payment_schedules.business_id', $business->id)
            ->where('payment_jobs.created_at', '>=', now()->subDays(30))
            ->get();

        $jobStatusCounts = $recentJobs->groupBy('status')
            ->map(fn ($group) => $group->count())
            ->toArray();

        return response()->json([
            'total_schedules' => $schedules->count(),
            'schedule_statuses' => $statusCounts,
            'upcoming_payments' => $upcomingSchedules,
            'recent_jobs_30_days' => [
                'total' => $recentJobs->count(),
                'by_status' => $jobStatusCounts,
                'total_amount' => round($recentJobs->where('status', 'succeeded')->sum('amount'), 2),
            ],
        ]);
    }

    /**
     * Get payroll summary (aggregated).
     */
    public function payrollSummary(Business $business): JsonResponse
    {
        $schedules = PayrollSchedule::where('business_id', $business->id)->get();

        $statusCounts = $schedules->groupBy('status')
            ->map(fn ($group) => $group->count())
            ->toArray();

        $upcomingSchedules = $schedules
            ->where('status', 'active')
            ->where('next_run_at', '>=', now())
            ->sortBy('next_run_at')
            ->take(5)
            ->map(fn ($schedule) => [
                'name' => $schedule->name,
                'next_run_at' => $schedule->next_run_at?->toDateTimeString(),
                'frequency' => $schedule->frequency,
                'employee_count' => $schedule->employees()->count(),
            ])
            ->values()
            ->toArray();

        // Get recent payroll jobs stats using JOIN instead of whereHas
        $recentJobs = PayrollJob::query()
            ->select(['payroll_jobs.*'])
            ->join('payroll_schedules', 'payroll_jobs.payroll_schedule_id', '=', 'payroll_schedules.id')
            ->where('payroll_schedules.business_id', $business->id)
            ->where('payroll_jobs.created_at', '>=', now()->subDays(30))
            ->get();

        $jobStatusCounts = $recentJobs->groupBy('status')
            ->map(fn ($group) => $group->count())
            ->toArray();

        return response()->json([
            'total_schedules' => $schedules->count(),
            'schedule_statuses' => $statusCounts,
            'upcoming_payroll' => $upcomingSchedules,
            'recent_jobs_30_days' => [
                'total' => $recentJobs->count(),
                'by_status' => $jobStatusCounts,
                'total_gross' => round($recentJobs->where('status', 'succeeded')->sum('gross_salary'), 2),
                'total_net' => round($recentJobs->where('status', 'succeeded')->sum('net_salary'), 2),
                'total_paye' => round($recentJobs->where('status', 'succeeded')->sum('paye'), 2),
                'total_uif' => round($recentJobs->where('status', 'succeeded')->sum('uif_employee') + $recentJobs->where('status', 'succeeded')->sum('uif_employer'), 2),
            ],
        ]);
    }

    /**
     * Get escrow balance.
     */
    public function escrowBalance(Business $business): JsonResponse
    {
        $balance = $this->escrowService->getAvailableBalance($business);

        // Calculate upcoming obligations (next 7 days)
        $upcomingPayments = PaymentSchedule::where('business_id', $business->id)
            ->active()
            ->where('next_run_at', '<=', now()->addDays(7))
            ->with('recipients')
            ->get()
            ->sum(function ($schedule) {
                return $schedule->amount * max($schedule->recipients()->count(), 1);
            });

        $upcomingPayroll = PayrollSchedule::where('business_id', $business->id)
            ->active()
            ->where('next_run_at', '<=', now()->addDays(7))
            ->with('employees')
            ->get()
            ->sum(function ($schedule) {
                return $schedule->employees->sum('gross_salary');
            });

        return response()->json([
            'current_balance' => round($balance, 2),
            'currency' => 'ZAR',
            'upcoming_obligations_7_days' => [
                'payments' => round($upcomingPayments, 2),
                'payroll' => round($upcomingPayroll, 2),
                'total' => round($upcomingPayments + $upcomingPayroll, 2),
            ],
            'is_sufficient' => $balance >= ($upcomingPayments + $upcomingPayroll),
        ]);
    }

    /**
     * Get compliance status summary.
     */
    public function complianceStatus(Business $business): JsonResponse
    {
        $currentMonth = now()->format('Y-m');
        $currentTaxYear = now()->month >= 3
            ? now()->year.'/'.(now()->year + 1)
            : (now()->year - 1).'/'.now()->year;

        // Check UI-19 status for current month
        $ui19 = ComplianceSubmission::where('business_id', $business->id)
            ->where('type', 'ui19')
            ->where('period', $currentMonth)
            ->first();

        // Check EMP201 status for current month
        $emp201 = ComplianceSubmission::where('business_id', $business->id)
            ->where('type', 'emp201')
            ->where('period', $currentMonth)
            ->first();

        // Check IRP5 status for current tax year
        $irp5Count = ComplianceSubmission::where('business_id', $business->id)
            ->where('type', 'irp5')
            ->where('period', $currentTaxYear)
            ->count();

        $employeeCount = Employee::where('business_id', $business->id)->count();

        return response()->json([
            'current_month' => $currentMonth,
            'current_tax_year' => $currentTaxYear,
            'ui19' => [
                'status' => $ui19?->status ?? 'not_generated',
                'submitted_at' => $ui19?->submitted_at?->toDateTimeString(),
            ],
            'emp201' => [
                'status' => $emp201?->status ?? 'not_generated',
                'submitted_at' => $emp201?->submitted_at?->toDateTimeString(),
            ],
            'irp5' => [
                'generated_count' => $irp5Count,
                'total_employees' => $employeeCount,
                'completion_percentage' => $employeeCount > 0 ? round(($irp5Count / $employeeCount) * 100, 1) : 0,
            ],
        ]);
    }

    /**
     * Get all business context in one call (for AI).
     */
    public function fullContext(Business $business): JsonResponse
    {
        return response()->json([
            'business' => $this->businessSummary($business)->getData(),
            'employees' => $this->employeesSummary($business)->getData(),
            'payments' => $this->paymentsSummary($business)->getData(),
            'payroll' => $this->payrollSummary($business)->getData(),
            'escrow' => $this->escrowBalance($business)->getData(),
            'compliance' => $this->complianceStatus($business)->getData(),
        ]);
    }
}
