<?php

namespace App\Jobs;

use App\Services\BulkPaymentProcessingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Batch Process Payroll Job
 *
 * Processes multiple payroll jobs using true bulk operations for bank-grade performance.
 * Uses BulkPaymentProcessingService to process entire batches atomically with minimal lock contention.
 */
class BatchProcessPayrollJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The maximum number of seconds a job can run before timing out.
     */
    public int $timeout = 600; // 10 minutes for bulk processing

    /**
     * Create a new job instance.
     */
    public function __construct(
        public array $payrollJobIds
    ) {}

    /**
     * Execute the job using true bulk processing.
     */
    public function handle(BulkPaymentProcessingService $bulkProcessingService): void
    {
        if (empty($this->payrollJobIds)) {
            return;
        }

        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);
        $peakMemoryBefore = memory_get_peak_usage(true);
        $totalJobs = count($this->payrollJobIds);

        Log::info('Starting bulk payroll processing', [
            'job_count' => $totalJobs,
            'job_ids' => array_slice($this->payrollJobIds, 0, 10), // Log first 10 for debugging
            'memory_usage_mb' => round($startMemory / 1024 / 1024, 2),
        ]);

        // For very large batches, split into smaller sub-batches to prevent timeouts
        $maxBatchSize = 1000; // Process max 1000 jobs per sub-batch
        $subBatches = array_chunk($this->payrollJobIds, $maxBatchSize);
        $totalProcessed = 0;
        $totalFailed = 0;

        try {
            foreach ($subBatches as $batchIndex => $subBatch) {
                $subBatchStartTime = microtime(true);

                // Log progress for large batches
                if (count($subBatches) > 1) {
                    Log::info('Processing sub-batch', [
                        'batch_index' => $batchIndex + 1,
                        'total_batches' => count($subBatches),
                        'sub_batch_size' => count($subBatch),
                        'progress_percent' => round((($batchIndex + 1) / count($subBatches)) * 100, 1),
                    ]);
                }

                // Use true bulk processing - processes all jobs atomically with minimal locks
                $result = $bulkProcessingService->processPayrollJobsBulk($subBatch);

                $totalProcessed += $result['processed'];
                $totalFailed += $result['failed'];

                $subBatchTime = round((microtime(true) - $subBatchStartTime) * 1000, 2);

                // Log progress after each sub-batch
                if (count($subBatches) > 1) {
                    Log::info('Sub-batch completed', [
                        'batch_index' => $batchIndex + 1,
                        'processed' => $result['processed'],
                        'failed' => $result['failed'],
                        'processing_time_ms' => $subBatchTime,
                    ]);
                }
            }

            // Aggregate results from all sub-batches
            $result = [
                'processed' => $totalProcessed,
                'failed' => $totalFailed,
                'stats' => [],
            ];

            $processingTime = round((microtime(true) - $startTime) * 1000, 2);
            $endMemory = memory_get_usage(true);
            $peakMemory = memory_get_peak_usage(true);
            $memoryUsed = $endMemory - $startMemory;
            $peakMemoryIncrease = $peakMemory - $peakMemoryBefore;

            Log::info('Bulk payroll processing completed', [
                'total_jobs' => $totalJobs,
                'processed' => $result['processed'],
                'failed' => $result['failed'],
                'processing_time_ms' => $processingTime,
                'throughput_jobs_per_sec' => $processingTime > 0 ? round($totalJobs / ($processingTime / 1000), 2) : 0,
                'memory_used_mb' => round($memoryUsed / 1024 / 1024, 2),
                'peak_memory_increase_mb' => round($peakMemoryIncrease / 1024 / 1024, 2),
                'memory_per_job_kb' => $totalJobs > 0 ? round($memoryUsed / $totalJobs / 1024, 2) : 0,
                'sub_batches' => count($subBatches),
            ]);
        } catch (\Exception $e) {
            Log::error('Bulk payroll processing failed', [
                'job_count' => $totalJobs,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'processed_before_failure' => $totalProcessed ?? 0,
                'failed_before_failure' => $totalFailed ?? 0,
            ]);

            throw $e; // Re-throw to trigger retry mechanism
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Batch payroll job permanently failed', [
            'payroll_job_ids' => $this->payrollJobIds,
            'exception' => $exception->getMessage(),
        ]);
    }
}
