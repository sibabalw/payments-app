<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentJob;
use App\Models\PayrollJob;
use App\Services\MetricsService;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PerformanceController extends Controller
{
    public function __construct(
        protected MetricsService $metricsService
    ) {}

    /**
     * Display performance monitoring page.
     */
    public function index(): Response
    {
        // Transaction success rates (last 30 days) — use single-quoted strings for PostgreSQL
        $driver = DB::connection()->getDriverName();
        $avgTimeExpr = $driver === 'pgsql'
            ? 'AVG(CASE WHEN processed_at IS NOT NULL THEN EXTRACT(EPOCH FROM (processed_at - created_at)) * 1000 ELSE NULL END) as avg_processing_time_ms'
            : 'AVG(CASE WHEN processed_at IS NOT NULL THEN TIMESTAMPDIFF(MICROSECOND, created_at, processed_at) / 1000 ELSE NULL END) as avg_processing_time_ms';

        $paymentStats = PaymentJob::query()
            ->where('processed_at', '>=', now()->subDays(30))
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN status = 'succeeded' THEN 1 ELSE 0 END) as succeeded,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                {$avgTimeExpr}
            ")
            ->first();

        $payrollStats = PayrollJob::query()
            ->where('processed_at', '>=', now()->subDays(30))
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN status = 'succeeded' THEN 1 ELSE 0 END) as succeeded,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                {$avgTimeExpr}
            ")
            ->first();

        $paymentSuccessRate = $paymentStats->total > 0
            ? round(($paymentStats->succeeded / $paymentStats->total) * 100, 2)
            : 0;

        $payrollSuccessRate = $payrollStats->total > 0
            ? round(($payrollStats->succeeded / $payrollStats->total) * 100, 2)
            : 0;

        // Queue statistics
        $queueStats = [
            'pending' => $this->metricsService->getQueueDepth('default'),
            'failed' => DB::table('failed_jobs')->count(),
        ];

        // Error rates by category (from audit logs)
        $errorRates = $this->metricsService->getErrorRates();

        // Response time trends (last 7 days)
        $responseTimeTrends = $this->getResponseTimeTrends();

        // Transaction volume trends
        $transactionTrends = $this->getTransactionTrends();

        // Top slow operations (last 24 hours)
        $slowOperations = $this->getSlowOperations();

        return Inertia::render('admin/performance/index', [
            'paymentMetrics' => [
                'total' => (int) $paymentStats->total,
                'succeeded' => (int) $paymentStats->succeeded,
                'failed' => (int) $paymentStats->failed,
                'success_rate' => $paymentSuccessRate,
                'avg_processing_time_ms' => $paymentStats->avg_processing_time_ms ? round($paymentStats->avg_processing_time_ms, 2) : 0,
            ],
            'payrollMetrics' => [
                'total' => (int) $payrollStats->total,
                'succeeded' => (int) $payrollStats->succeeded,
                'failed' => (int) $payrollStats->failed,
                'success_rate' => $payrollSuccessRate,
                'avg_processing_time_ms' => $payrollStats->avg_processing_time_ms ? round($payrollStats->avg_processing_time_ms, 2) : 0,
            ],
            'queueStats' => $queueStats,
            'errorRates' => $errorRates,
            'responseTimeTrends' => $responseTimeTrends,
            'transactionTrends' => $transactionTrends,
            'slowOperations' => $slowOperations,
        ]);
    }

    /**
     * Get response time trends for the last 7 days.
     */
    private function getResponseTimeTrends(): array
    {
        $trends = [];

        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $start = $date.' 00:00:00';
            $end = $date.' 23:59:59';

            $paymentAvg = PaymentJob::query()
                ->whereBetween('processed_at', [$start, $end])
                ->whereNotNull('processed_at')
                ->selectRaw('AVG(TIMESTAMPDIFF(MICROSECOND, created_at, processed_at) / 1000) as avg_time')
                ->value('avg_time') ?? 0;

            $payrollAvg = PayrollJob::query()
                ->whereBetween('processed_at', [$start, $end])
                ->whereNotNull('processed_at')
                ->selectRaw('AVG(TIMESTAMPDIFF(MICROSECOND, created_at, processed_at) / 1000) as avg_time')
                ->value('avg_time') ?? 0;

            $trends[] = [
                'date' => $date,
                'payment_avg_ms' => round($paymentAvg, 2),
                'payroll_avg_ms' => round($payrollAvg, 2),
            ];
        }

        return $trends;
    }

    /**
     * Get transaction volume trends.
     */
    private function getTransactionTrends(): array
    {
        $trends = [];

        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $start = $date.' 00:00:00';
            $end = $date.' 23:59:59';

            $paymentCount = PaymentJob::query()
                ->whereBetween('created_at', [$start, $end])
                ->count();

            $payrollCount = PayrollJob::query()
                ->whereBetween('created_at', [$start, $end])
                ->count();

            $trends[] = [
                'date' => $date,
                'payments' => $paymentCount,
                'payroll' => $payrollCount,
            ];
        }

        return $trends;
    }

    /**
     * Get top slow operations from the last 24 hours.
     */
    private function getSlowOperations(): array
    {
        // Get slow payment jobs
        $slowPayments = PaymentJob::query()
            ->where('processed_at', '>=', now()->subDay())
            ->whereNotNull('processed_at')
            ->selectRaw('
                id,
                "payment" as type,
                TIMESTAMPDIFF(MICROSECOND, created_at, processed_at) / 1000 as processing_time_ms,
                status,
                created_at
            ')
            ->orderByDesc('processing_time_ms')
            ->limit(5)
            ->get()
            ->map(fn ($job) => [
                'id' => $job->id,
                'type' => 'payment',
                'processing_time_ms' => round($job->processing_time_ms, 2),
                'status' => $job->status,
                'created_at' => $job->created_at->toIso8601String(),
            ]);

        // Get slow payroll jobs
        $slowPayroll = PayrollJob::query()
            ->where('processed_at', '>=', now()->subDay())
            ->whereNotNull('processed_at')
            ->selectRaw('
                id,
                "payroll" as type,
                TIMESTAMPDIFF(MICROSECOND, created_at, processed_at) / 1000 as processing_time_ms,
                status,
                created_at
            ')
            ->orderByDesc('processing_time_ms')
            ->limit(5)
            ->get()
            ->map(fn ($job) => [
                'id' => $job->id,
                'type' => 'payroll',
                'processing_time_ms' => round($job->processing_time_ms, 2),
                'status' => $job->status,
                'created_at' => $job->created_at->toIso8601String(),
            ]);

        return $slowPayments->merge($slowPayroll)
            ->sortByDesc('processing_time_ms')
            ->take(10)
            ->values()
            ->toArray();
    }
}
