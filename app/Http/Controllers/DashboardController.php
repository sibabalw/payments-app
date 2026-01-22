<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\Employee;
use App\Models\PaymentJob;
use App\Models\PaymentSchedule;
use App\Models\PayrollJob;
use App\Models\PayrollSchedule;
use App\Models\Recipient;
use App\Services\EscrowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function __construct(
        private EscrowService $escrowService
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();

        // Admins skip onboarding - redirect to admin dashboard
        if ($user->is_admin) {
            return redirect()->route('admin.dashboard');
        }

        // Redirect to onboarding if not completed
        if (! $user->onboarding_completed_at) {
            $hasBusinesses = $user->businesses()->exists() || $user->ownedBusinesses()->exists();
            if ($hasBusinesses) {
                $user->update(['onboarding_completed_at' => now()]);
            } else {
                return redirect()->route('onboarding.index');
            }
        }

        // Auto-select business if needed
        if (! $user->current_business_id) {
            $firstBusiness = $user->ownedBusinesses()->first() ?? $user->businesses()->first();
            if ($firstBusiness) {
                $user->update(['current_business_id' => $firstBusiness->id]);
                $user->refresh();
            }
        }

        $businessId = $user->current_business_id;

        // Get business IDs for the user (single query)
        $userBusinessIds = $this->getUserBusinessIds($user);

        // Get basic metrics (optimized with single queries)
        $metrics = $this->getBasicMetrics($businessId, $userBusinessIds);

        // Get financial data for current month
        $financial = $this->getFinancialMetrics($businessId, $userBusinessIds);

        // Get trends data (optimized bulk queries)
        $frequency = $request->get('frequency', 'monthly');
        $trendsData = $this->getTrendsData($businessId, $userBusinessIds, $frequency);

        // Get daily trends (single query with GROUP BY)
        $dailyTrends = $this->getDailyTrends($businessId, $userBusinessIds);

        // Get weekly trends (single query with GROUP BY)
        $weeklyTrends = $this->getWeeklyTrends($businessId, $userBusinessIds);

        // Get success rate trends
        $successRateTrends = $this->getSuccessRateTrends($businessId, $userBusinessIds, $frequency);

        // Get top recipients and employees (optimized)
        $topRecipients = $this->getTopRecipients($businessId, $userBusinessIds);
        $topEmployees = $this->getTopEmployees($businessId, $userBusinessIds);

        // Get upcoming schedules (optimized with withCount)
        $upcomingPayments = $this->getUpcomingSchedules($businessId, $userBusinessIds);

        // Get recent jobs (optimized with limit)
        $recentJobs = $this->getRecentJobs($businessId, $userBusinessIds);

        // Get business info
        $businessInfo = $this->getBusinessInfo($user, $businessId);
        $escrowBalance = $businessInfo['escrow_balance'] ?? 0;

        // Month over month growth
        $monthOverMonthGrowth = $this->getMonthOverMonthGrowth($businessId, $userBusinessIds);

        // Average amounts
        $avgPaymentAmount = $financial['avg_payment_amount'] ?? 0;
        $avgPayrollAmount = $financial['avg_payroll_amount'] ?? 0;

        // Businesses count
        $businessesCount = count($userBusinessIds);

        return Inertia::render('dashboard', [
            'metrics' => $metrics,
            'financial' => [
                'total_payments_this_month' => $financial['total_payments'],
                'total_payroll_this_month' => $financial['total_payroll'],
                'total_fees_this_month' => $financial['total_fees'],
                'total_processed_this_month' => $financial['total_payments'] + $financial['total_payroll'],
                'success_rate' => $financial['success_rate'],
                'total_jobs_this_month' => $financial['total_jobs'],
            ],
            'monthlyTrends' => $trendsData,
            'statusBreakdown' => $metrics['status_breakdown'],
            'jobTypeComparison' => [
                'payments' => $metrics['succeeded_payment_jobs'],
                'payroll' => $metrics['succeeded_payroll_jobs'],
            ],
            'dailyTrends' => $dailyTrends,
            'weeklyTrends' => $weeklyTrends,
            'successRateTrends' => $successRateTrends,
            'topRecipients' => $topRecipients,
            'topEmployees' => $topEmployees,
            'monthOverMonthGrowth' => $monthOverMonthGrowth,
            'avgPaymentAmount' => $avgPaymentAmount,
            'avgPayrollAmount' => $avgPayrollAmount,
            'upcomingPayments' => $upcomingPayments,
            'recentJobs' => $recentJobs,
            'escrowBalance' => $escrowBalance,
            'selectedBusiness' => $businessInfo ? ['id' => $businessInfo['id'], 'name' => $businessInfo['name']] : null,
            'businessInfo' => $businessInfo,
            'businessesCount' => $businessesCount,
        ]);
    }

    private function getUserBusinessIds($user): array
    {
        $ownedIds = $user->ownedBusinesses()->pluck('id')->toArray();
        $associatedIds = $user->businesses()->pluck('businesses.id')->toArray();

        return array_unique(array_merge($ownedIds, $associatedIds));
    }

    private function getBasicMetrics(?int $businessId, array $userBusinessIds): array
    {
        $businessFilter = $businessId ? [$businessId] : $userBusinessIds;

        // Payment schedules - single query with conditional counts
        $paymentScheduleStats = PaymentSchedule::query()
            ->whereIn('business_id', $businessFilter)
            ->selectRaw('COUNT(*) as total, SUM(CASE WHEN status = "active" THEN 1 ELSE 0 END) as active')
            ->first();

        // Payroll schedules - single query with conditional counts
        $payrollScheduleStats = PayrollSchedule::query()
            ->whereIn('business_id', $businessFilter)
            ->selectRaw('COUNT(*) as total, SUM(CASE WHEN status = "active" THEN 1 ELSE 0 END) as active')
            ->first();

        // Payment jobs - single query with all status counts using JOIN
        $paymentJobStats = PaymentJob::query()
            ->join('payment_schedules', 'payment_jobs.payment_schedule_id', '=', 'payment_schedules.id')
            ->whereIn('payment_schedules.business_id', $businessFilter)
            ->selectRaw('
                SUM(CASE WHEN payment_jobs.status = "pending" THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN payment_jobs.status = "processing" THEN 1 ELSE 0 END) as processing,
                SUM(CASE WHEN payment_jobs.status = "succeeded" THEN 1 ELSE 0 END) as succeeded,
                SUM(CASE WHEN payment_jobs.status = "failed" THEN 1 ELSE 0 END) as failed
            ')
            ->first();

        // Payroll jobs - single query with all status counts using JOIN
        $payrollJobStats = PayrollJob::query()
            ->join('payroll_schedules', 'payroll_jobs.payroll_schedule_id', '=', 'payroll_schedules.id')
            ->whereIn('payroll_schedules.business_id', $businessFilter)
            ->selectRaw('
                SUM(CASE WHEN payroll_jobs.status = "pending" THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN payroll_jobs.status = "processing" THEN 1 ELSE 0 END) as processing,
                SUM(CASE WHEN payroll_jobs.status = "succeeded" THEN 1 ELSE 0 END) as succeeded,
                SUM(CASE WHEN payroll_jobs.status = "failed" THEN 1 ELSE 0 END) as failed
            ')
            ->first();

        $pending = ($paymentJobStats->pending ?? 0) + ($payrollJobStats->pending ?? 0);
        $processing = ($paymentJobStats->processing ?? 0) + ($payrollJobStats->processing ?? 0);
        $succeeded = ($paymentJobStats->succeeded ?? 0) + ($payrollJobStats->succeeded ?? 0);
        $failed = ($paymentJobStats->failed ?? 0) + ($payrollJobStats->failed ?? 0);

        return [
            'total_schedules' => ($paymentScheduleStats->total ?? 0) + ($payrollScheduleStats->total ?? 0),
            'active_schedules' => ($paymentScheduleStats->active ?? 0) + ($payrollScheduleStats->active ?? 0),
            'pending_jobs' => $pending,
            'processing_jobs' => $processing,
            'succeeded_jobs' => $succeeded,
            'failed_jobs' => $failed,
            'succeeded_payment_jobs' => $paymentJobStats->succeeded ?? 0,
            'succeeded_payroll_jobs' => $payrollJobStats->succeeded ?? 0,
            'status_breakdown' => [
                'succeeded' => $succeeded,
                'failed' => $failed,
                'pending' => $pending,
                'processing' => $processing,
            ],
        ];
    }

    private function getFinancialMetrics(?int $businessId, array $userBusinessIds): array
    {
        $businessFilter = $businessId ? [$businessId] : $userBusinessIds;
        $currentMonthStart = now()->startOfMonth();
        $currentMonthEnd = now()->endOfMonth();

        // Payment jobs financial stats - single query
        $paymentStats = PaymentJob::query()
            ->join('payment_schedules', 'payment_jobs.payment_schedule_id', '=', 'payment_schedules.id')
            ->whereIn('payment_schedules.business_id', $businessFilter)
            ->whereBetween('payment_jobs.processed_at', [$currentMonthStart, $currentMonthEnd])
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN payment_jobs.status = "succeeded" THEN 1 ELSE 0 END) as succeeded,
                SUM(CASE WHEN payment_jobs.status = "succeeded" THEN payment_jobs.amount ELSE 0 END) as total_amount,
                SUM(CASE WHEN payment_jobs.status = "succeeded" THEN COALESCE(payment_jobs.fee, 0) ELSE 0 END) as total_fees,
                AVG(CASE WHEN payment_jobs.status = "succeeded" THEN payment_jobs.amount ELSE NULL END) as avg_amount
            ')
            ->first();

        // Payroll jobs financial stats - single query
        $payrollStats = PayrollJob::query()
            ->join('payroll_schedules', 'payroll_jobs.payroll_schedule_id', '=', 'payroll_schedules.id')
            ->whereIn('payroll_schedules.business_id', $businessFilter)
            ->whereBetween('payroll_jobs.processed_at', [$currentMonthStart, $currentMonthEnd])
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN payroll_jobs.status = "succeeded" THEN 1 ELSE 0 END) as succeeded,
                SUM(CASE WHEN payroll_jobs.status = "succeeded" THEN payroll_jobs.net_salary ELSE 0 END) as total_amount,
                SUM(CASE WHEN payroll_jobs.status = "succeeded" THEN COALESCE(payroll_jobs.fee, 0) ELSE 0 END) as total_fees,
                AVG(CASE WHEN payroll_jobs.status = "succeeded" THEN payroll_jobs.net_salary ELSE NULL END) as avg_amount
            ')
            ->first();

        $totalJobs = ($paymentStats->total ?? 0) + ($payrollStats->total ?? 0);
        $succeededJobs = ($paymentStats->succeeded ?? 0) + ($payrollStats->succeeded ?? 0);
        $successRate = $totalJobs > 0 ? round(($succeededJobs / $totalJobs) * 100, 1) : 0;

        return [
            'total_payments' => (float) ($paymentStats->total_amount ?? 0),
            'total_payroll' => (float) ($payrollStats->total_amount ?? 0),
            'total_fees' => (float) (($paymentStats->total_fees ?? 0) + ($payrollStats->total_fees ?? 0)),
            'total_jobs' => $totalJobs,
            'success_rate' => $successRate,
            'avg_payment_amount' => (float) ($paymentStats->avg_amount ?? 0),
            'avg_payroll_amount' => (float) ($payrollStats->avg_amount ?? 0),
        ];
    }

    private function getTrendsData(?int $businessId, array $userBusinessIds, string $frequency): array
    {
        $businessFilter = $businessId ? [$businessId] : $userBusinessIds;

        // Determine date format and range based on frequency
        $config = $this->getFrequencyConfig($frequency);

        // Payment trends - single query with GROUP BY
        $paymentTrends = PaymentJob::query()
            ->join('payment_schedules', 'payment_jobs.payment_schedule_id', '=', 'payment_schedules.id')
            ->whereIn('payment_schedules.business_id', $businessFilter)
            ->where('payment_jobs.status', 'succeeded')
            ->where('payment_jobs.processed_at', '>=', $config['start_date'])
            ->selectRaw("{$config['date_format']} as period, SUM(payment_jobs.amount) as total")
            ->groupBy('period')
            ->pluck('total', 'period')
            ->toArray();

        // Payroll trends - single query with GROUP BY
        $payrollTrends = PayrollJob::query()
            ->join('payroll_schedules', 'payroll_jobs.payroll_schedule_id', '=', 'payroll_schedules.id')
            ->whereIn('payroll_schedules.business_id', $businessFilter)
            ->where('payroll_jobs.status', 'succeeded')
            ->where('payroll_jobs.processed_at', '>=', $config['start_date'])
            ->selectRaw("{$config['date_format']} as period, SUM(payroll_jobs.net_salary) as total")
            ->groupBy('period')
            ->pluck('total', 'period')
            ->toArray();

        // Build the result with all periods
        $result = [];
        foreach ($config['periods'] as $period) {
            $payments = (float) ($paymentTrends[$period['key']] ?? 0);
            $payroll = (float) ($payrollTrends[$period['key']] ?? 0);
            $result[] = [
                $config['label_key'] => $period['label'],
                'payments' => $payments,
                'payroll' => $payroll,
                'total' => $payments + $payroll,
            ];
        }

        return $result;
    }

    private function getDailyTrends(?int $businessId, array $userBusinessIds): array
    {
        $businessFilter = $businessId ? [$businessId] : $userBusinessIds;
        $startDate = now()->subDays(29)->startOfDay();

        // Payment daily trends - combined into single query with both total and count
        // Note: DATE() in SELECT is fine; the index (status, processed_at) handles the WHERE clause
        $paymentData = PaymentJob::query()
            ->join('payment_schedules', 'payment_jobs.payment_schedule_id', '=', 'payment_schedules.id')
            ->whereIn('payment_schedules.business_id', $businessFilter)
            ->where('payment_jobs.status', 'succeeded')
            ->where('payment_jobs.processed_at', '>=', $startDate)
            ->selectRaw('DATE(payment_jobs.processed_at) as date, SUM(payment_jobs.amount) as total, COUNT(*) as count')
            ->groupBy('date')
            ->get()
            ->keyBy('date')
            ->toArray();

        // Payroll daily trends - combined into single query
        $payrollData = PayrollJob::query()
            ->join('payroll_schedules', 'payroll_jobs.payroll_schedule_id', '=', 'payroll_schedules.id')
            ->whereIn('payroll_schedules.business_id', $businessFilter)
            ->where('payroll_jobs.status', 'succeeded')
            ->where('payroll_jobs.processed_at', '>=', $startDate)
            ->selectRaw('DATE(payroll_jobs.processed_at) as date, SUM(payroll_jobs.net_salary) as total, COUNT(*) as count')
            ->groupBy('date')
            ->get()
            ->keyBy('date')
            ->toArray();

        // Build result for all 30 days
        $result = [];
        for ($i = 29; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $displayDate = now()->subDays($i)->format('M d');
            $payments = (float) ($paymentData[$date]['total'] ?? 0);
            $payroll = (float) ($payrollData[$date]['total'] ?? 0);
            $paymentCount = (int) ($paymentData[$date]['count'] ?? 0);
            $payrollCount = (int) ($payrollData[$date]['count'] ?? 0);

            $result[] = [
                'date' => $displayDate,
                'payments' => $payments,
                'payroll' => $payroll,
                'total' => $payments + $payroll,
                'jobs_count' => $paymentCount + $payrollCount,
            ];
        }

        return $result;
    }

    private function getWeeklyTrends(?int $businessId, array $userBusinessIds): array
    {
        $businessFilter = $businessId ? [$businessId] : $userBusinessIds;
        $startDate = now()->subWeeks(11)->startOfWeek();

        // Payment weekly trends - single query
        $paymentWeekly = PaymentJob::query()
            ->join('payment_schedules', 'payment_jobs.payment_schedule_id', '=', 'payment_schedules.id')
            ->whereIn('payment_schedules.business_id', $businessFilter)
            ->where('payment_jobs.status', 'succeeded')
            ->where('payment_jobs.processed_at', '>=', $startDate)
            ->selectRaw('YEARWEEK(payment_jobs.processed_at, 1) as week, SUM(payment_jobs.amount) as total')
            ->groupBy('week')
            ->pluck('total', 'week')
            ->toArray();

        // Payroll weekly trends - single query
        $payrollWeekly = PayrollJob::query()
            ->join('payroll_schedules', 'payroll_jobs.payroll_schedule_id', '=', 'payroll_schedules.id')
            ->whereIn('payroll_schedules.business_id', $businessFilter)
            ->where('payroll_jobs.status', 'succeeded')
            ->where('payroll_jobs.processed_at', '>=', $startDate)
            ->selectRaw('YEARWEEK(payroll_jobs.processed_at, 1) as week, SUM(payroll_jobs.net_salary) as total')
            ->groupBy('week')
            ->pluck('total', 'week')
            ->toArray();

        // Build result for all 12 weeks
        $result = [];
        for ($i = 11; $i >= 0; $i--) {
            $weekStart = now()->subWeeks($i)->startOfWeek();
            $weekEnd = now()->subWeeks($i)->endOfWeek();
            $yearWeek = $weekStart->format('oW'); // ISO year-week

            $payments = (float) ($paymentWeekly[$yearWeek] ?? 0);
            $payroll = (float) ($payrollWeekly[$yearWeek] ?? 0);

            $result[] = [
                'week' => $weekStart->format('M d').' - '.$weekEnd->format('M d'),
                'payments' => $payments,
                'payroll' => $payroll,
                'total' => $payments + $payroll,
            ];
        }

        return $result;
    }

    private function getSuccessRateTrends(?int $businessId, array $userBusinessIds, string $frequency): array
    {
        $businessFilter = $businessId ? [$businessId] : $userBusinessIds;
        $config = $this->getFrequencyConfig($frequency);

        // Payment success stats - single query with GROUP BY
        $paymentStats = PaymentJob::query()
            ->join('payment_schedules', 'payment_jobs.payment_schedule_id', '=', 'payment_schedules.id')
            ->whereIn('payment_schedules.business_id', $businessFilter)
            ->where('payment_jobs.processed_at', '>=', $config['start_date'])
            ->selectRaw("
                {$config['date_format']} as period,
                COUNT(*) as total,
                SUM(CASE WHEN payment_jobs.status = 'succeeded' THEN 1 ELSE 0 END) as succeeded,
                SUM(CASE WHEN payment_jobs.status = 'failed' THEN 1 ELSE 0 END) as failed
            ")
            ->groupBy('period')
            ->get()
            ->keyBy('period')
            ->toArray();

        // Payroll success stats - single query with GROUP BY
        $payrollStats = PayrollJob::query()
            ->join('payroll_schedules', 'payroll_jobs.payroll_schedule_id', '=', 'payroll_schedules.id')
            ->whereIn('payroll_schedules.business_id', $businessFilter)
            ->where('payroll_jobs.processed_at', '>=', $config['start_date'])
            ->selectRaw("
                {$config['date_format']} as period,
                COUNT(*) as total,
                SUM(CASE WHEN payroll_jobs.status = 'succeeded' THEN 1 ELSE 0 END) as succeeded,
                SUM(CASE WHEN payroll_jobs.status = 'failed' THEN 1 ELSE 0 END) as failed
            ")
            ->groupBy('period')
            ->get()
            ->keyBy('period')
            ->toArray();

        // Build result
        $result = [];
        foreach ($config['periods'] as $period) {
            $key = $period['key'];
            $paymentData = $paymentStats[$key] ?? ['total' => 0, 'succeeded' => 0, 'failed' => 0];
            $payrollData = $payrollStats[$key] ?? ['total' => 0, 'succeeded' => 0, 'failed' => 0];

            $total = ($paymentData['total'] ?? 0) + ($payrollData['total'] ?? 0);
            $succeeded = ($paymentData['succeeded'] ?? 0) + ($payrollData['succeeded'] ?? 0);
            $failed = ($paymentData['failed'] ?? 0) + ($payrollData['failed'] ?? 0);
            $successRate = $total > 0 ? round(($succeeded / $total) * 100, 1) : 0;

            $result[] = [
                $config['label_key'] => $period['label'],
                'success_rate' => $successRate,
                'succeeded' => $succeeded,
                'failed' => $failed,
                'total' => $total,
            ];
        }

        return $result;
    }

    private function getTopRecipients(?int $businessId, array $userBusinessIds): array
    {
        $businessFilter = $businessId ? [$businessId] : $userBusinessIds;
        $startDate = now()->subDays(30)->startOfDay();

        return PaymentJob::query()
            ->join('payment_schedules', 'payment_jobs.payment_schedule_id', '=', 'payment_schedules.id')
            ->join('recipients', 'payment_jobs.recipient_id', '=', 'recipients.id')
            ->whereIn('payment_schedules.business_id', $businessFilter)
            ->where('payment_jobs.status', 'succeeded')
            ->where('payment_jobs.processed_at', '>=', $startDate)
            ->selectRaw('
                recipients.name,
                SUM(payment_jobs.amount) as total_amount,
                COUNT(*) as jobs_count,
                AVG(payment_jobs.amount) as average_amount
            ')
            ->groupBy('recipients.id', 'recipients.name')
            ->orderByDesc('total_amount')
            ->limit(10)
            ->get()
            ->map(fn ($row) => [
                'name' => $row->name ?? 'Unknown',
                'total_amount' => (float) $row->total_amount,
                'jobs_count' => (int) $row->jobs_count,
                'average_amount' => (float) $row->average_amount,
            ])
            ->toArray();
    }

    private function getTopEmployees(?int $businessId, array $userBusinessIds): array
    {
        $businessFilter = $businessId ? [$businessId] : $userBusinessIds;
        $startDate = now()->subDays(30)->startOfDay();

        return PayrollJob::query()
            ->join('payroll_schedules', 'payroll_jobs.payroll_schedule_id', '=', 'payroll_schedules.id')
            ->join('employees', 'payroll_jobs.employee_id', '=', 'employees.id')
            ->whereIn('payroll_schedules.business_id', $businessFilter)
            ->where('payroll_jobs.status', 'succeeded')
            ->where('payroll_jobs.processed_at', '>=', $startDate)
            ->selectRaw('
                employees.name,
                SUM(payroll_jobs.net_salary) as total_amount,
                COUNT(*) as jobs_count,
                AVG(payroll_jobs.net_salary) as average_amount
            ')
            ->groupBy('employees.id', 'employees.name')
            ->orderByDesc('total_amount')
            ->limit(10)
            ->get()
            ->map(fn ($row) => [
                'name' => $row->name ?? 'Unknown',
                'total_amount' => (float) $row->total_amount,
                'jobs_count' => (int) $row->jobs_count,
                'average_amount' => (float) $row->average_amount,
            ])
            ->toArray();
    }

    private function getUpcomingSchedules(?int $businessId, array $userBusinessIds): array
    {
        $businessFilter = $businessId ? [$businessId] : $userBusinessIds;

        // Payment schedules with count
        $paymentSchedules = PaymentSchedule::query()
            ->whereIn('business_id', $businessFilter)
            ->where('status', 'active')
            ->where('next_run_at', '>=', now())
            ->withCount('recipients')
            ->orderBy('next_run_at')
            ->limit(6)
            ->get()
            ->map(fn ($schedule) => [
                'id' => $schedule->id,
                'name' => $schedule->name,
                'next_run_at' => $schedule->next_run_at,
                'amount' => $schedule->amount,
                'currency' => $schedule->currency,
                'type' => 'payment',
                'recipients_count' => $schedule->recipients_count,
            ]);

        // Payroll schedules with count
        $payrollSchedules = PayrollSchedule::query()
            ->whereIn('business_id', $businessFilter)
            ->where('status', 'active')
            ->where('next_run_at', '>=', now())
            ->withCount('employees')
            ->orderBy('next_run_at')
            ->limit(6)
            ->get()
            ->map(fn ($schedule) => [
                'id' => $schedule->id,
                'name' => $schedule->name,
                'next_run_at' => $schedule->next_run_at,
                'amount' => null,
                'currency' => 'ZAR',
                'type' => 'payroll',
                'employees_count' => $schedule->employees_count,
            ]);

        return $paymentSchedules->concat($payrollSchedules)
            ->sortBy('next_run_at')
            ->take(6)
            ->values()
            ->toArray();
    }

    private function getRecentJobs(?int $businessId, array $userBusinessIds): array
    {
        $businessFilter = $businessId ? [$businessId] : $userBusinessIds;

        // Recent payment jobs
        $paymentJobs = PaymentJob::query()
            ->join('payment_schedules', 'payment_jobs.payment_schedule_id', '=', 'payment_schedules.id')
            ->leftJoin('recipients', 'payment_jobs.recipient_id', '=', 'recipients.id')
            ->whereIn('payment_schedules.business_id', $businessFilter)
            ->select([
                'payment_jobs.id',
                'recipients.name as recipient_name',
                'payment_schedules.name as schedule_name',
                'payment_jobs.amount',
                'payment_jobs.currency',
                'payment_jobs.status',
                'payment_jobs.processed_at',
                DB::raw("'payment' as type"),
            ])
            ->orderByDesc('payment_jobs.processed_at')
            ->limit(4)
            ->get();

        // Recent payroll jobs
        $payrollJobs = PayrollJob::query()
            ->join('payroll_schedules', 'payroll_jobs.payroll_schedule_id', '=', 'payroll_schedules.id')
            ->leftJoin('employees', 'payroll_jobs.employee_id', '=', 'employees.id')
            ->whereIn('payroll_schedules.business_id', $businessFilter)
            ->select([
                'payroll_jobs.id',
                'employees.name as recipient_name',
                'payroll_schedules.name as schedule_name',
                'payroll_jobs.net_salary as amount',
                'payroll_jobs.currency',
                'payroll_jobs.status',
                'payroll_jobs.processed_at',
                DB::raw("'payroll' as type"),
            ])
            ->orderByDesc('payroll_jobs.processed_at')
            ->limit(4)
            ->get();

        return $paymentJobs->concat($payrollJobs)
            ->sortByDesc('processed_at')
            ->take(4)
            ->map(fn ($job) => [
                'id' => $job->id,
                'name' => $job->recipient_name ?? 'Unknown',
                'schedule_name' => $job->schedule_name ?? 'Unknown',
                'amount' => $job->amount,
                'currency' => $job->currency,
                'status' => $job->status,
                'processed_at' => $job->processed_at,
                'type' => $job->type,
            ])
            ->values()
            ->toArray();
    }

    private function getBusinessInfo($user, ?int $businessId): ?array
    {
        $business = null;

        if ($businessId) {
            $business = Business::find($businessId);
        }

        if (! $business) {
            $business = $user->businesses()->first() ?? $user->ownedBusinesses()->first();
        }

        if (! $business) {
            return null;
        }

        // Get counts in single queries
        $employeesCount = Employee::where('business_id', $business->id)->count();
        $paymentSchedulesCount = PaymentSchedule::where('business_id', $business->id)->count();
        $payrollSchedulesCount = PayrollSchedule::where('business_id', $business->id)->count();
        $recipientsCount = Recipient::where('business_id', $business->id)->count();

        $logoUrl = $business->logo ? Storage::disk('public')->url($business->logo) : null;
        $escrowBalance = $this->escrowService->getAvailableBalance($business);

        return [
            'id' => $business->id,
            'name' => $business->name,
            'logo' => $logoUrl,
            'status' => $business->status,
            'business_type' => $business->business_type,
            'email' => $business->email,
            'phone' => $business->phone,
            'escrow_balance' => (float) $escrowBalance,
            'employees_count' => $employeesCount,
            'payment_schedules_count' => $paymentSchedulesCount,
            'payroll_schedules_count' => $payrollSchedulesCount,
            'recipients_count' => $recipientsCount,
        ];
    }

    private function getMonthOverMonthGrowth(?int $businessId, array $userBusinessIds): float
    {
        $businessFilter = $businessId ? [$businessId] : $userBusinessIds;

        $lastMonthStart = now()->subMonth()->startOfMonth();
        $lastMonthEnd = now()->subMonth()->endOfMonth();
        $thisMonthStart = now()->startOfMonth();
        $thisMonthEnd = now()->endOfMonth();

        // Last month totals - single query
        $lastMonthPayments = PaymentJob::query()
            ->join('payment_schedules', 'payment_jobs.payment_schedule_id', '=', 'payment_schedules.id')
            ->whereIn('payment_schedules.business_id', $businessFilter)
            ->where('payment_jobs.status', 'succeeded')
            ->whereBetween('payment_jobs.processed_at', [$lastMonthStart, $lastMonthEnd])
            ->sum('payment_jobs.amount');

        $lastMonthPayroll = PayrollJob::query()
            ->join('payroll_schedules', 'payroll_jobs.payroll_schedule_id', '=', 'payroll_schedules.id')
            ->whereIn('payroll_schedules.business_id', $businessFilter)
            ->where('payroll_jobs.status', 'succeeded')
            ->whereBetween('payroll_jobs.processed_at', [$lastMonthStart, $lastMonthEnd])
            ->sum('payroll_jobs.net_salary');

        // This month totals - single query
        $thisMonthPayments = PaymentJob::query()
            ->join('payment_schedules', 'payment_jobs.payment_schedule_id', '=', 'payment_schedules.id')
            ->whereIn('payment_schedules.business_id', $businessFilter)
            ->where('payment_jobs.status', 'succeeded')
            ->whereBetween('payment_jobs.processed_at', [$thisMonthStart, $thisMonthEnd])
            ->sum('payment_jobs.amount');

        $thisMonthPayroll = PayrollJob::query()
            ->join('payroll_schedules', 'payroll_jobs.payroll_schedule_id', '=', 'payroll_schedules.id')
            ->whereIn('payroll_schedules.business_id', $businessFilter)
            ->where('payroll_jobs.status', 'succeeded')
            ->whereBetween('payroll_jobs.processed_at', [$thisMonthStart, $thisMonthEnd])
            ->sum('payroll_jobs.net_salary');

        $lastMonthTotal = (float) $lastMonthPayments + (float) $lastMonthPayroll;
        $thisMonthTotal = (float) $thisMonthPayments + (float) $thisMonthPayroll;

        if ($lastMonthTotal > 0) {
            return round((($thisMonthTotal - $lastMonthTotal) / $lastMonthTotal) * 100, 1);
        }

        return 0;
    }

    private function getFrequencyConfig(string $frequency): array
    {
        $periods = [];
        $dateFormat = '';
        $labelKey = '';
        $startDate = now();

        switch ($frequency) {
            case 'weekly':
                $dateFormat = 'YEARWEEK(processed_at, 1)';
                $labelKey = 'week';
                $startDate = now()->subWeeks(11)->startOfWeek();
                for ($i = 11; $i >= 0; $i--) {
                    $weekStart = now()->subWeeks($i)->startOfWeek();
                    $weekEnd = now()->subWeeks($i)->endOfWeek();
                    $periods[] = [
                        'key' => $weekStart->format('oW'),
                        'label' => $weekStart->format('M d').' - '.$weekEnd->format('M d'),
                    ];
                }
                break;

            case 'monthly':
                $dateFormat = 'DATE_FORMAT(processed_at, "%Y-%m")';
                $labelKey = 'month';
                $startDate = now()->subMonths(5)->startOfMonth();
                for ($i = 5; $i >= 0; $i--) {
                    $monthStart = now()->subMonths($i)->startOfMonth();
                    $periods[] = [
                        'key' => $monthStart->format('Y-m'),
                        'label' => $monthStart->format('M Y'),
                    ];
                }
                break;

            case 'quarterly':
                $dateFormat = 'CONCAT(YEAR(processed_at), "-Q", QUARTER(processed_at))';
                $labelKey = 'quarter';
                $startDate = now()->subQuarters(7)->startOfQuarter();
                for ($i = 7; $i >= 0; $i--) {
                    $quarterStart = now()->subQuarters($i)->startOfQuarter();
                    $quarterNum = ceil($quarterStart->month / 3);
                    $periods[] = [
                        'key' => $quarterStart->format('Y').'-Q'.$quarterNum,
                        'label' => 'Q'.$quarterNum.' '.$quarterStart->format('Y'),
                    ];
                }
                break;

            case 'yearly':
                $dateFormat = 'YEAR(processed_at)';
                $labelKey = 'year';
                $startDate = now()->subYears(4)->startOfYear();
                for ($i = 4; $i >= 0; $i--) {
                    $yearStart = now()->subYears($i)->startOfYear();
                    $periods[] = [
                        'key' => $yearStart->format('Y'),
                        'label' => $yearStart->format('Y'),
                    ];
                }
                break;
        }

        return [
            'date_format' => $dateFormat,
            'label_key' => $labelKey,
            'start_date' => $startDate,
            'periods' => $periods,
        ];
    }
}
