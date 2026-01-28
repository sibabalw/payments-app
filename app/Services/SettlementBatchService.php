<?php

namespace App\Services;

use App\Helpers\LogContext;
use App\Models\PaymentJob;
use App\Models\PayrollJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Settlement Batch Service
 *
 * Provides high-performance batch processing of settlement windows using true bulk operations.
 * Uses BulkPaymentProcessingService for bank-grade performance with minimal lock contention.
 * Processes jobs in priority order (payroll first, then payments).
 */
class SettlementBatchService
{
    protected BulkPaymentProcessingService $bulkProcessingService;

    protected FinancialLedgerService $ledgerService;

    protected IdempotencyService $idempotencyService;

    protected MetricsService $metricsService;

    protected ReconciliationService $reconciliationService;

    public function __construct(
        ?BulkPaymentProcessingService $bulkProcessingService = null,
        ?FinancialLedgerService $ledgerService = null,
        ?IdempotencyService $idempotencyService = null,
        ?MetricsService $metricsService = null,
        ?ReconciliationService $reconciliationService = null
    ) {
        $this->bulkProcessingService = $bulkProcessingService ?? app(BulkPaymentProcessingService::class);
        $this->ledgerService = $ledgerService ?? app(FinancialLedgerService::class);
        $this->idempotencyService = $idempotencyService ?? app(IdempotencyService::class);
        $this->metricsService = $metricsService ?? app(MetricsService::class);
        $this->reconciliationService = $reconciliationService ?? app(ReconciliationService::class);
    }

    /**
     * Process all jobs in a settlement window using true bulk operations
     *
     * Uses BulkPaymentProcessingService for bank-grade performance with minimal lock contention.
     * Pre-calculates all balances before processing to ensure consistency.
     * Includes idempotency, atomic status transitions, and bulk ledger posting.
     *
     * @param  int  $windowId  Settlement window ID
     * @return array Results with counts and statistics
     */
    public function processWindow(int $windowId): array
    {
        // Idempotency key for settlement window processing
        $idempotencyKey = 'settlement_window_'.$windowId;

        return $this->idempotencyService->execute($idempotencyKey, function () use ($windowId) {
            $startTime = microtime(true);

            // Atomically transition window status: pending → processing
            $windowUpdated = DB::table('settlement_windows')
                ->where('id', $windowId)
                ->where('status', 'pending')
                ->update([
                    'status' => 'processing',
                    'updated_at' => now(),
                ]);

            if ($windowUpdated === 0) {
                // Window is not pending - may already be processing or settled
                $window = DB::table('settlement_windows')->find($windowId);
                $currentStatus = $window?->status ?? 'unknown';

                LogContext::warning('Settlement window not in pending status', LogContext::create(
                    null,
                    null,
                    null,
                    'settlement_process',
                    null,
                    [
                        'window_id' => $windowId,
                        'current_status' => $currentStatus,
                    ]
                ));

                // Return empty stats if already processed
                if ($currentStatus === 'settled') {
                    return [
                        'window_id' => $windowId,
                        'payment_jobs_processed' => 0,
                        'payroll_jobs_processed' => 0,
                        'payment_jobs_failed' => 0,
                        'payroll_jobs_failed' => 0,
                        'already_processed' => true,
                    ];
                }
            }

            try {
                // Load all pending jobs in the window - optimized query
                $paymentJobs = PaymentJob::where('settlement_window_id', $windowId)
                    ->where('status', 'pending')
                    ->select(['id'])
                    ->pluck('id')
                    ->toArray();

                $payrollJobs = PayrollJob::where('settlement_window_id', $windowId)
                    ->where('status', 'pending')
                    ->select(['id'])
                    ->pluck('id')
                    ->toArray();

                if (empty($paymentJobs) && empty($payrollJobs)) {
                    LogContext::info('Settlement window has no pending jobs', LogContext::create(
                        null,
                        null,
                        null,
                        'settlement_process',
                        null,
                        ['window_id' => $windowId]
                    ));

                    // Mark window as settled if no jobs
                    DB::table('settlement_windows')
                        ->where('id', $windowId)
                        ->update([
                            'status' => 'settled',
                            'settled_at' => now(),
                        ]);

                    return [
                        'window_id' => $windowId,
                        'payment_jobs_processed' => 0,
                        'payroll_jobs_processed' => 0,
                        'payment_jobs_failed' => 0,
                        'payroll_jobs_failed' => 0,
                    ];
                }

                $stats = [
                    'window_id' => $windowId,
                    'payment_jobs_processed' => 0,
                    'payroll_jobs_processed' => 0,
                    'payment_jobs_failed' => 0,
                    'payroll_jobs_failed' => 0,
                ];

                // Process payroll jobs first (higher priority) using bulk processing
                if (! empty($payrollJobs)) {
                    $payrollResult = $this->bulkProcessingService->processPayrollJobsBulk($payrollJobs);
                    $stats['payroll_jobs_processed'] = $payrollResult['processed'];
                    $stats['payroll_jobs_failed'] = $payrollResult['failed'];
                }

                // Process payment jobs second using bulk processing
                if (! empty($paymentJobs)) {
                    $paymentResult = $this->bulkProcessingService->processPaymentJobsBulk($paymentJobs);
                    $stats['payment_jobs_processed'] = $paymentResult['processed'];
                    $stats['payment_jobs_failed'] = $paymentResult['failed'];
                }

                // Post all ledger entries for successful jobs in bulk
                // Get correlation IDs from all successful jobs in this window
                $postingStartTime = microtime(true);
                $correlationIds = $this->getCorrelationIdsForWindow($windowId);
                if (! empty($correlationIds)) {
                    $postingResult = $this->ledgerService->postBulkTransactions($correlationIds);
                    $stats['ledger_entries_posted'] = $postingResult['posted'];
                    $stats['ledger_entries_failed'] = $postingResult['failed'];

                    $postingTime = round((microtime(true) - $postingStartTime) * 1000, 2);
                    $this->metricsService->recordLedgerPosting(
                        $postingResult['posted'],
                        $postingResult['failed'],
                        $postingTime
                    );

                    if ($postingResult['failed'] > 0) {
                        LogContext::warning('Some ledger entries failed to post', LogContext::create(
                            null,
                            null,
                            null,
                            'settlement_process',
                            null,
                            [
                                'window_id' => $windowId,
                                'failed_count' => $postingResult['failed'],
                                'errors' => $postingResult['errors'],
                            ]
                        ));
                    }
                }

                // Atomically transition window status: processing → settled
                DB::table('settlement_windows')
                    ->where('id', $windowId)
                    ->where('status', 'processing')
                    ->update([
                        'status' => 'settled',
                        'settled_at' => now(),
                        'updated_at' => now(),
                    ]);

                // Trigger automatic reconciliation for businesses in this window
                // Run asynchronously to avoid blocking settlement completion
                $this->triggerReconciliationForWindow($windowId);

                $processingTime = round((microtime(true) - $startTime) * 1000, 2);
                $totalJobs = count($paymentJobs) + count($payrollJobs);
                $totalSucceeded = $stats['payment_jobs_processed'] + $stats['payroll_jobs_processed'];
                $totalFailed = $stats['payment_jobs_failed'] + $stats['payroll_jobs_failed'];

                // Record settlement window metrics
                $this->metricsService->recordSettlementWindow(
                    $windowId,
                    $processingTime,
                    $totalJobs,
                    $totalSucceeded,
                    $totalFailed
                );

                LogContext::info('Settlement window processed using bulk operations', LogContext::create(
                    null,
                    null,
                    null,
                    'settlement_process',
                    null,
                    [
                        'window_id' => $windowId,
                        'stats' => $stats,
                        'processing_time_ms' => $processingTime,
                        'throughput_jobs_per_sec' => $processingTime > 0 ? round($totalJobs / ($processingTime / 1000), 2) : 0,
                    ]
                ));

                return $stats;
            } catch (\Exception $e) {
                // Mark window as failed on error
                DB::table('settlement_windows')
                    ->where('id', $windowId)
                    ->where('status', 'processing')
                    ->update([
                        'status' => 'failed',
                        'updated_at' => now(),
                    ]);

                LogContext::error('Settlement window processing failed', LogContext::create(
                    null,
                    null,
                    null,
                    'settlement_process',
                    null,
                    [
                        'window_id' => $windowId,
                        'exception' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]
                ));

                throw $e;
            }
        }, 86400); // 24 hour TTL for idempotency
    }

    /**
     * Get correlation IDs for all successful jobs in a settlement window
     *
     * @param  int  $windowId  Settlement window ID
     * @return array Array of correlation IDs
     */
    protected function getCorrelationIdsForWindow(int $windowId): array
    {
        // Get correlation IDs from ledger entries for jobs in this window
        $payrollJobIds = PayrollJob::where('settlement_window_id', $windowId)
            ->where('status', 'succeeded')
            ->pluck('id')
            ->toArray();

        $paymentJobIds = PaymentJob::where('settlement_window_id', $windowId)
            ->where('status', 'succeeded')
            ->pluck('id')
            ->toArray();

        $correlationIds = [];

        if (! empty($payrollJobIds)) {
            $payrollCorrelationIds = DB::table('financial_ledger')
                ->whereIn('reference_id', $payrollJobIds)
                ->where('reference_type', 'App\Models\PayrollJob')
                ->where('posting_state', 'PENDING')
                ->distinct()
                ->pluck('correlation_id')
                ->toArray();

            $correlationIds = array_merge($correlationIds, $payrollCorrelationIds);
        }

        if (! empty($paymentJobIds)) {
            $paymentCorrelationIds = DB::table('financial_ledger')
                ->whereIn('reference_id', $paymentJobIds)
                ->where('reference_type', 'App\Models\PaymentJob')
                ->where('posting_state', 'PENDING')
                ->distinct()
                ->pluck('correlation_id')
                ->toArray();

            $correlationIds = array_merge($correlationIds, $paymentCorrelationIds);
        }

        return array_unique($correlationIds);
    }

    /**
     * Trigger reconciliation for all businesses in a settlement window
     *
     * Runs asynchronously to avoid blocking settlement completion.
     * Only reconciles businesses that had successful jobs in the window.
     *
     * @param  int  $windowId  Settlement window ID
     */
    protected function triggerReconciliationForWindow(int $windowId): void
    {
        // Get unique business IDs from successful jobs in this window
        $payrollBusinessIds = PayrollJob::where('settlement_window_id', $windowId)
            ->where('status', 'succeeded')
            ->join('payroll_schedules', 'payroll_jobs.payroll_schedule_id', '=', 'payroll_schedules.id')
            ->distinct()
            ->pluck('payroll_schedules.business_id')
            ->toArray();

        $paymentBusinessIds = PaymentJob::where('settlement_window_id', $windowId)
            ->where('status', 'succeeded')
            ->join('payment_schedules', 'payment_jobs.payment_schedule_id', '=', 'payment_schedules.id')
            ->distinct()
            ->pluck('payment_schedules.business_id')
            ->toArray();

        $businessIds = array_unique(array_merge($payrollBusinessIds, $paymentBusinessIds));

        if (empty($businessIds)) {
            return;
        }

        // Dispatch reconciliation jobs asynchronously
        // Use afterCommit to ensure reconciliation happens after settlement is committed
        DB::afterCommit(function () use ($businessIds, $windowId) {
            foreach ($businessIds as $businessId) {
                try {
                    // Dispatch reconciliation job for each business
                    \App\Jobs\ReconcileBalanceJob::dispatch($businessId)->onQueue('low');
                } catch (\Exception $e) {
                    // Log but don't fail - reconciliation is important but not critical for settlement
                    LogContext::warning('Failed to dispatch reconciliation job', LogContext::create(
                        null,
                        $businessId,
                        null,
                        'settlement_process',
                        null,
                        [
                            'window_id' => $windowId,
                            'error' => $e->getMessage(),
                        ]
                    ));
                }
            }

            LogContext::info('Reconciliation jobs dispatched for settlement window', LogContext::create(
                null,
                null,
                null,
                'settlement_process',
                null,
                [
                    'window_id' => $windowId,
                    'business_count' => count($businessIds),
                ]
            ));
        });
    }
}
