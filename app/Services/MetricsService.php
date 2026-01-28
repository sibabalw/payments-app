<?php

namespace App\Services;

use App\Helpers\LogContext;
use Illuminate\Support\Facades\DB;

class MetricsService
{
    /**
     * Record transaction processing time
     *
     * @param  string  $operation  Operation type (payment_process, payroll_process, settlement_process, etc.)
     * @param  float  $duration  Duration in milliseconds
     * @param  bool  $success  Whether the operation succeeded
     * @param  array  $metadata  Additional metadata (job_count, business_id, etc.)
     */
    public function recordProcessingTime(string $operation, float $duration, bool $success = true, array $metadata = []): void
    {
        LogContext::info('Transaction processing time', LogContext::create(
            null,
            $metadata['business_id'] ?? null,
            $metadata['job_id'] ?? null,
            'metrics',
            null,
            array_merge([
                'operation' => $operation,
                'duration_ms' => $duration,
                'success' => $success,
            ], $metadata)
        ));
    }

    /**
     * Record transaction success/failure
     *
     * @param  string  $type  Transaction type (payment, payroll, settlement)
     * @param  bool  $success  Whether the transaction succeeded
     * @param  string|null  $errorCategory  Error category if failed
     * @param  array  $metadata  Additional metadata
     */
    public function recordTransaction(string $type, bool $success, ?string $errorCategory = null, array $metadata = []): void
    {
        LogContext::info('Transaction recorded', LogContext::create(
            $metadata['correlation_id'] ?? null,
            $metadata['business_id'] ?? null,
            $metadata['job_id'] ?? null,
            'metrics',
            null,
            array_merge([
                'type' => $type,
                'success' => $success,
                'error_category' => $errorCategory,
            ], $metadata)
        ));
    }

    /**
     * Record settlement window metrics
     *
     * @param  int  $windowId  Settlement window ID
     * @param  float  $processingTime  Processing time in milliseconds
     * @param  int  $totalJobs  Total jobs processed
     * @param  int  $succeeded  Number of succeeded jobs
     * @param  int  $failed  Number of failed jobs
     */
    public function recordSettlementWindow(int $windowId, float $processingTime, int $totalJobs, int $succeeded, int $failed): void
    {
        $successRate = $totalJobs > 0 ? ($succeeded / $totalJobs) * 100 : 0;
        $throughput = $processingTime > 0 ? ($totalJobs / ($processingTime / 1000)) : 0;

        LogContext::info('Settlement window metrics', LogContext::create(
            null,
            null,
            null,
            'metrics',
            null,
            [
                'window_id' => $windowId,
                'processing_time_ms' => $processingTime,
                'total_jobs' => $totalJobs,
                'succeeded' => $succeeded,
                'failed' => $failed,
                'success_rate_percent' => round($successRate, 2),
                'throughput_jobs_per_sec' => round($throughput, 2),
            ]
        ));
    }

    /**
     * Record ledger posting metrics
     *
     * @param  int  $entriesPosted  Number of entries posted
     * @param  int  $entriesFailed  Number of entries that failed to post
     * @param  float  $postingTime  Posting time in milliseconds
     */
    public function recordLedgerPosting(int $entriesPosted, int $entriesFailed, float $postingTime): void
    {
        LogContext::info('Ledger posting metrics', LogContext::create(
            null,
            null,
            null,
            'metrics',
            null,
            [
                'entries_posted' => $entriesPosted,
                'entries_failed' => $entriesFailed,
                'posting_time_ms' => $postingTime,
                'throughput_entries_per_sec' => $postingTime > 0 ? round($entriesPosted / ($postingTime / 1000), 2) : 0,
            ]
        ));
    }

    /**
     * Get transaction success rate
     */
    public function getSuccessRate(string $type, ?\DateTime $since = null): float
    {
        // This would typically query a metrics table or use a monitoring service
        // For now, we'll use logs as a simple implementation
        return 0.95; // Placeholder
    }

    /**
     * Get balance reconciliation accuracy
     */
    public function getReconciliationAccuracy(): float
    {
        // Placeholder - would query reconciliation results
        return 0.99;
    }

    /**
     * Get queue depth
     */
    public function getQueueDepth(string $queue = 'default'): int
    {
        return DB::table('jobs')->where('queue', $queue)->count();
    }

    /**
     * Get error rates by type
     */
    public function getErrorRates(?\DateTime $since = null): array
    {
        // Placeholder - would aggregate from error logs or metrics table
        return [
            'balance' => 0.01,
            'concurrency' => 0.02,
            'validation' => 0.005,
            'network' => 0.01,
            'other' => 0.005,
        ];
    }
}
