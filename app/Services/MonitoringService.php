<?php

namespace App\Services;

use App\Models\Business;
use App\Models\PaymentJob;
use App\Models\PayrollJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Monitoring Service
 *
 * Provides metrics and health checks for the system.
 * Hooks are provided for external monitoring systems (StatsD, Prometheus).
 */
class MonitoringService
{
    /**
     * Get transaction metrics
     *
     * @param  int  $days  Number of days to look back
     * @return array Metrics
     */
    public function getTransactionMetrics(int $days = 7): array
    {
        $startDate = now()->subDays($days);

        // Payroll metrics
        $payrollTotal = PayrollJob::where('created_at', '>=', $startDate)->count();
        $payrollSucceeded = PayrollJob::where('created_at', '>=', $startDate)
            ->where('status', 'succeeded')
            ->count();
        $payrollFailed = PayrollJob::where('created_at', '>=', $startDate)
            ->where('status', 'failed')
            ->count();

        // Payment metrics
        $paymentTotal = PaymentJob::where('created_at', '>=', $startDate)->count();
        $paymentSucceeded = PaymentJob::where('created_at', '>=', $startDate)
            ->where('status', 'succeeded')
            ->count();
        $paymentFailed = PaymentJob::where('created_at', '>=', $startDate)
            ->where('status', 'failed')
            ->count();

        // Calculate success rates
        $payrollSuccessRate = $payrollTotal > 0 ? ($payrollSucceeded / $payrollTotal) * 100 : 0;
        $paymentSuccessRate = $paymentTotal > 0 ? ($paymentSucceeded / $paymentTotal) * 100 : 0;

        // Average processing time (if processed_at is available)
        $payrollAvgTime = $this->calculateAverageProcessingTime(PayrollJob::class, $startDate);
        $paymentAvgTime = $this->calculateAverageProcessingTime(PaymentJob::class, $startDate);

        return [
            'period_days' => $days,
            'payroll' => [
                'total' => $payrollTotal,
                'succeeded' => $payrollSucceeded,
                'failed' => $payrollFailed,
                'success_rate' => round($payrollSuccessRate, 2),
                'avg_processing_time_ms' => $payrollAvgTime,
            ],
            'payment' => [
                'total' => $paymentTotal,
                'succeeded' => $paymentSucceeded,
                'failed' => $paymentFailed,
                'success_rate' => round($paymentSuccessRate, 2),
                'avg_processing_time_ms' => $paymentAvgTime,
            ],
        ];
    }

    /**
     * Calculate average processing time
     *
     * @param  string  $modelClass  Model class
     * @param  \Carbon\Carbon  $startDate  Start date
     * @return float|null Average processing time in milliseconds
     */
    protected function calculateAverageProcessingTime(string $modelClass, \Carbon\Carbon $startDate): ?float
    {
        $driver = DB::connection()->getDriverName();

        try {
            if ($driver === 'pgsql') {
                $result = DB::selectOne("
                    SELECT AVG(EXTRACT(EPOCH FROM (processed_at - created_at)) * 1000) as avg_time
                    FROM {$this->getTableName($modelClass)}
                    WHERE created_at >= ?
                    AND processed_at IS NOT NULL
                    AND status = 'succeeded'
                ", [$startDate]);

                return $result ? (float) $result->avg_time : null;
            } else {
                // MySQL
                $result = DB::selectOne("
                    SELECT AVG(TIMESTAMPDIFF(MICROSECOND, created_at, processed_at) / 1000) as avg_time
                    FROM {$this->getTableName($modelClass)}
                    WHERE created_at >= ?
                    AND processed_at IS NOT NULL
                    AND status = 'succeeded'
                ", [$startDate]);

                return $result ? (float) $result->avg_time : null;
            }
        } catch (\Exception $e) {
            Log::warning('Failed to calculate average processing time', [
                'model' => $modelClass,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Get table name from model class
     */
    protected function getTableName(string $modelClass): string
    {
        return (new $modelClass)->getTable();
    }

    /**
     * Run health checks
     *
     * @return array Health check results
     */
    public function runHealthChecks(): array
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'queue' => $this->checkQueue(),
            'balance_accuracy' => $this->checkBalanceAccuracy(),
            'stuck_jobs' => $this->checkStuckJobs(),
        ];

        $allHealthy = collect($checks)->every(fn ($check) => $check['healthy'] === true);

        return [
            'overall' => $allHealthy ? 'healthy' : 'unhealthy',
            'checks' => $checks,
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * Check database connectivity
     */
    protected function checkDatabase(): array
    {
        try {
            DB::connection()->getPdo();
            $connected = true;
            $error = null;
        } catch (\Exception $e) {
            $connected = false;
            $error = $e->getMessage();
        }

        return [
            'healthy' => $connected,
            'message' => $connected ? 'Database connection OK' : 'Database connection failed',
            'error' => $error,
        ];
    }

    /**
     * Check queue depth
     */
    protected function checkQueue(): array
    {
        try {
            $queueDepth = DB::table('jobs')
                ->whereIn('queue', ['high', 'normal', 'default'])
                ->count();

            $healthy = $queueDepth < 10000; // Threshold: 10k jobs
            $message = $healthy
                ? "Queue depth OK ({$queueDepth} jobs)"
                : "Queue depth high ({$queueDepth} jobs)";

            return [
                'healthy' => $healthy,
                'message' => $message,
                'queue_depth' => $queueDepth,
            ];
        } catch (\Exception $e) {
            return [
                'healthy' => false,
                'message' => 'Failed to check queue depth',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check balance accuracy (sample check)
     */
    protected function checkBalanceAccuracy(): array
    {
        try {
            // Sample a few businesses and check balance accuracy
            $sampleSize = 10;
            $businesses = Business::inRandomOrder()->limit($sampleSize)->get();

            $discrepancies = 0;
            foreach ($businesses as $business) {
                $escrowService = app(EscrowService::class);
                $ledgerService = app(FinancialLedgerService::class);

                $storedBalance = $escrowService->getAvailableBalance($business, false, false);
                $ledgerBalance = $ledgerService->getAccountBalance($business, FinancialLedgerService::ACCOUNT_ESCROW, true);

                $diff = abs($storedBalance - $ledgerBalance);
                if ($diff > 0.01) {
                    $discrepancies++;
                }
            }

            $discrepancyRate = ($discrepancies / $sampleSize) * 100;
            $healthy = $discrepancyRate < 10; // Less than 10% discrepancy rate

            return [
                'healthy' => $healthy,
                'message' => $healthy
                    ? "Balance accuracy OK ({$discrepancies}/{$sampleSize} discrepancies)"
                    : "Balance accuracy issue ({$discrepancies}/{$sampleSize} discrepancies)",
                'discrepancy_rate' => round($discrepancyRate, 2),
                'sample_size' => $sampleSize,
            ];
        } catch (\Exception $e) {
            return [
                'healthy' => false,
                'message' => 'Failed to check balance accuracy',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check for stuck jobs
     */
    protected function checkStuckJobs(): array
    {
        try {
            $threshold = now()->subMinutes(30);

            $stuckPayroll = PayrollJob::where('status', 'processing')
                ->where('updated_at', '<', $threshold)
                ->count();

            $stuckPayment = PaymentJob::where('status', 'processing')
                ->where('updated_at', '<', $threshold)
                ->count();

            $totalStuck = $stuckPayroll + $stuckPayment;
            $healthy = $totalStuck < 100; // Threshold: 100 stuck jobs

            return [
                'healthy' => $healthy,
                'message' => $healthy
                    ? "Stuck jobs OK ({$totalStuck} stuck)"
                    : "Stuck jobs detected ({$totalStuck} stuck)",
                'stuck_payroll' => $stuckPayroll,
                'stuck_payment' => $stuckPayment,
                'total_stuck' => $totalStuck,
            ];
        } catch (\Exception $e) {
            return [
                'healthy' => false,
                'message' => 'Failed to check stuck jobs',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get metrics in format suitable for external monitoring (StatsD, Prometheus)
     *
     * @return array Metrics in key-value format
     */
    public function getMetricsForMonitoring(): array
    {
        $metrics = $this->getTransactionMetrics(1); // Last 24 hours
        $health = $this->runHealthChecks();

        return [
            'payroll.total' => $metrics['payroll']['total'],
            'payroll.succeeded' => $metrics['payroll']['succeeded'],
            'payroll.failed' => $metrics['payroll']['failed'],
            'payroll.success_rate' => $metrics['payroll']['success_rate'],
            'payroll.avg_processing_time_ms' => $metrics['payroll']['avg_processing_time_ms'] ?? 0,
            'payment.total' => $metrics['payment']['total'],
            'payment.succeeded' => $metrics['payment']['succeeded'],
            'payment.failed' => $metrics['payment']['failed'],
            'payment.success_rate' => $metrics['payment']['success_rate'],
            'payment.avg_processing_time_ms' => $metrics['payment']['avg_processing_time_ms'] ?? 0,
            'health.overall' => $health['overall'] === 'healthy' ? 1 : 0,
            'health.database' => $health['checks']['database']['healthy'] ? 1 : 0,
            'health.queue' => $health['checks']['queue']['healthy'] ? 1 : 0,
            'health.balance_accuracy' => $health['checks']['balance_accuracy']['healthy'] ? 1 : 0,
            'health.stuck_jobs' => $health['checks']['stuck_jobs']['healthy'] ? 1 : 0,
        ];
    }
}
