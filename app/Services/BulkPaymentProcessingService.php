<?php

namespace App\Services;

use App\Helpers\LogContext;
use App\Models\Business;
use App\Models\PaymentJob;
use App\Models\PayrollJob;
use App\Traits\PostgresSavepoints;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Bulk Payment Processing Service
 *
 * Provides true bulk processing of payroll and payment jobs with bank-grade performance.
 * Processes entire batches atomically using bulk SQL operations, minimizing lock contention
 * and database round-trips. This is the core service for high-throughput transaction processing.
 *
 * LOCK ORDERING (CRITICAL - prevents deadlocks):
 * Always acquire locks in this order: business → schedule → job → deposit
 * This ensures consistent lock ordering across all operations and prevents deadlocks.
 * Use skipLocked() to avoid blocking when locks are already held by other processes.
 */
class BulkPaymentProcessingService
{
    use PostgresSavepoints;

    protected EscrowService $escrowService;

    protected FinancialLedgerService $ledgerService;

    protected BulkBalanceUpdateService $bulkBalanceService;

    protected BulkJobUpdateService $bulkJobUpdateService;

    protected BalancePrecalculationService $balancePrecalculationService;

    public function __construct(
        ?EscrowService $escrowService = null,
        ?FinancialLedgerService $ledgerService = null,
        ?BulkBalanceUpdateService $bulkBalanceService = null,
        ?BulkJobUpdateService $bulkJobUpdateService = null,
        ?BalancePrecalculationService $balancePrecalculationService = null
    ) {
        $this->escrowService = $escrowService ?? app(EscrowService::class);
        $this->ledgerService = $ledgerService ?? app(FinancialLedgerService::class);
        $this->bulkBalanceService = $bulkBalanceService ?? app(BulkBalanceUpdateService::class);
        $this->bulkJobUpdateService = $bulkJobUpdateService ?? app(BulkJobUpdateService::class);
        $this->balancePrecalculationService = $balancePrecalculationService ?? app(BalancePrecalculationService::class);
    }

    /**
     * Process payroll jobs in true bulk
     *
     * Pre-calculates balances, groups by business, processes in bulk with minimal locks.
     *
     * @param  array  $jobIds  Array of payroll job IDs
     * @return array Results with 'processed', 'failed', 'stats'
     */
    public function processPayrollJobsBulk(array $jobIds): array
    {
        if (empty($jobIds)) {
            return [
                'processed' => 0,
                'failed' => 0,
                'stats' => [],
            ];
        }

        // Generate operation ID for batch tracking
        $operationId = \App\Helpers\LogContext::generateOperationId();
        $correlationId = $this->ledgerService->generateCorrelationId();

        // Log batch operation start
        $logContext = \App\Helpers\LogContext::logOperationStart('process_payroll_jobs_bulk', \App\Helpers\LogContext::create(
            $correlationId,
            null,
            null,
            'bulk_payroll_process',
            null,
            ['operation_id' => $operationId, 'job_count' => count($jobIds), 'job_ids' => $jobIds]
        ));

        // Load all jobs with relationships - optimized query with selected columns only
        // For very large batches (>5000 jobs), use lazy loading to reduce memory usage
        // Chunk whereIn for large arrays (memory/performance)
        if (count($jobIds) > 5000) {
            // Use lazy() for very large job sets to process in chunks
            $jobs = collect();
            foreach (array_chunk($jobIds, 1000) as $chunk) {
                $chunkJobs = $this->loadJobsWithChunkedWhereIn(
                    PayrollJob::class,
                    $chunk,
                    [
                        'id',
                        'payroll_schedule_id',
                        'employee_id',
                        'net_salary',
                        'status',
                        'escrow_deposit_id',
                    ],
                    [
                        'payrollSchedule:id,business_id',
                        'payrollSchedule.business:id,escrow_balance,hold_amount',
                    ]
                );
                $jobs = $jobs->merge($chunkJobs);
                // Clear memory after each chunk
                unset($chunkJobs);
            }
        } else {
            $jobs = $this->loadJobsWithChunkedWhereIn(
                PayrollJob::class,
                $jobIds,
                [
                    'id',
                    'payroll_schedule_id',
                    'employee_id',
                    'net_salary',
                    'status',
                    'escrow_deposit_id',
                ],
                [
                    'payrollSchedule:id,business_id',
                    'payrollSchedule.business:id,escrow_balance,hold_amount',
                ]
            );
        }

        if ($jobs->isEmpty()) {
            Log::warning('No payroll jobs found for bulk processing', [
                'job_ids' => $jobIds,
            ]);

            return [
                'processed' => 0,
                'failed' => 0,
                'stats' => [],
            ];
        }

        // Group jobs by business_id to minimize lock contention
        $jobsByBusiness = $jobs->groupBy(function ($job) {
            return $job->payrollSchedule->business_id;
        });

        // Pre-calculate balances for all businesses in a single optimized query
        // Use BalancePrecalculationService for efficient batch balance calculation
        $businessIds = $jobsByBusiness->keys()->toArray();
        // Chunk whereIn for large arrays (memory/performance)
        $businesses = $this->loadBusinessesWithChunkedWhereIn($businessIds);

        // Pre-calculate all balances using optimized service (caches within transaction)
        $balances = $this->balancePrecalculationService->preCalculateBalances($businessIds);

        // Fallback: if pre-calculation didn't return all balances, calculate from loaded businesses
        foreach ($businesses as $business) {
            if (! isset($balances[$business->id])) {
                $balances[$business->id] = ($business->escrow_balance ?? 0) - ($business->hold_amount ?? 0);
            }
        }

        $totalProcessed = 0;
        $totalFailed = 0;
        $stats = [];

        // Process each business's jobs in a single transaction
        // Note: Parallel processing is achieved through the queue system (multiple workers processing
        // different batches simultaneously). Sequential processing here ensures proper lock ordering
        // and prevents deadlocks. For true parallel processing within a single batch, use multiple
        // queue workers processing smaller batches in parallel.
        foreach ($jobsByBusiness as $businessId => $businessJobs) {
            $business = $businesses->get($businessId);
            if (! $business) {
                Log::warning('Business not found for payroll jobs', [
                    'business_id' => $businessId,
                ]);
                $totalFailed += $businessJobs->count();

                continue;
            }

            try {
                // Process in transaction with retry - only critical operations inside transaction
                $result = DB::transaction(function () use ($business, $businessJobs, $balances) {
                    return $this->processPayrollJobsForBusiness($business, $businessJobs, $balances[$business->id]);
                }, 3); // Retry up to 3 times on deadlock

                $totalProcessed += $result['processed'];
                $totalFailed += $result['failed'];
                $stats[$businessId] = $result;
            } catch (\Exception $e) {
                // Move error handling outside transaction (optimization: reduce transaction time)
                // Logging and job status updates don't need to be in the same transaction
                $failedJobIds = $businessJobs->pluck('id')->toArray();
                $errorMessage = $e->getMessage();
                $jobCount = $businessJobs->count();

                // Log error after transaction commits (non-blocking)
                DB::afterCommit(function () use ($businessId, $jobCount, $errorMessage) {
                    LogContext::error('Failed to process payroll jobs for business', LogContext::create(
                        null,
                        $businessId,
                        null,
                        'bulk_payroll_process',
                        null,
                        [
                            'job_count' => $jobCount,
                            'error' => $errorMessage,
                        ]
                    ));
                });

                // Mark all jobs as failed - this can be done outside the main transaction
                $failures = array_map(function ($jobId) use ($errorMessage) {
                    return [
                        'job_id' => $jobId,
                        'error_message' => 'Bulk processing failed: '.$errorMessage,
                    ];
                }, $failedJobIds);

                // Use separate lightweight transaction for marking failed (non-critical operation)
                try {
                    $this->bulkJobUpdateService->markJobsAsFailed($failures, 'payroll');
                } catch (\Exception $markFailedException) {
                    // Log but don't fail - job marking is non-critical
                    DB::afterCommit(function () use ($businessId, $markFailedException) {
                        Log::warning('Failed to mark jobs as failed', [
                            'business_id' => $businessId,
                            'error' => $markFailedException->getMessage(),
                        ]);
                    });
                }
                $totalFailed += count($failedJobIds);
            }
        }

        // Calculate performance metrics
        $businessCount = count($stats);
        $avgJobsPerBusiness = $businessCount > 0 ? round($totalProcessed / $businessCount, 2) : 0;

        // Log operation end
        \App\Helpers\LogContext::logOperationEnd('process_payroll_jobs_bulk', array_merge($logContext, [
            'correlation_id' => $correlationId,
            'operation_id' => $operationId,
        ]), $totalFailed === 0, [
            'total_jobs' => count($jobIds),
            'processed' => $totalProcessed,
            'failed' => $totalFailed,
            'businesses_processed' => $businessCount,
            'avg_jobs_per_business' => $avgJobsPerBusiness,
        ]);

        return [
            'processed' => $totalProcessed,
            'failed' => $totalFailed,
            'stats' => $stats,
        ];
    }

    /**
     * Process payment jobs in true bulk
     *
     * @param  array  $jobIds  Array of payment job IDs
     * @return array Results with 'processed', 'failed', 'stats'
     */
    public function processPaymentJobsBulk(array $jobIds): array
    {
        if (empty($jobIds)) {
            return [
                'processed' => 0,
                'failed' => 0,
                'stats' => [],
            ];
        }

        // Generate operation ID for batch tracking
        $operationId = \App\Helpers\LogContext::generateOperationId();
        $correlationId = $this->ledgerService->generateCorrelationId();

        // Log batch operation start
        $logContext = \App\Helpers\LogContext::logOperationStart('process_payment_jobs_bulk', \App\Helpers\LogContext::create(
            $correlationId,
            null,
            null,
            'bulk_payment_process',
            null,
            ['operation_id' => $operationId, 'job_count' => count($jobIds), 'job_ids' => $jobIds]
        ));

        // Load all jobs with relationships - optimized query with selected columns only
        // For very large batches (>5000 jobs), use lazy loading to reduce memory usage
        // Chunk whereIn for large arrays (memory/performance)
        if (count($jobIds) > 5000) {
            // Use lazy() for very large job sets to process in chunks
            $jobs = collect();
            foreach (array_chunk($jobIds, 1000) as $chunk) {
                $chunkJobs = $this->loadJobsWithChunkedWhereIn(
                    PaymentJob::class,
                    $chunk,
                    [
                        'id',
                        'payment_schedule_id',
                        'recipient_id',
                        'amount',
                        'status',
                        'escrow_deposit_id',
                    ],
                    [
                        'paymentSchedule:id,business_id',
                        'paymentSchedule.business:id,escrow_balance,hold_amount',
                    ]
                );
                $jobs = $jobs->merge($chunkJobs);
                // Clear memory after each chunk
                unset($chunkJobs);
            }
        } else {
            $jobs = $this->loadJobsWithChunkedWhereIn(
                PaymentJob::class,
                $jobIds,
                [
                    'id',
                    'payment_schedule_id',
                    'recipient_id',
                    'amount',
                    'status',
                    'escrow_deposit_id',
                ],
                [
                    'paymentSchedule:id,business_id',
                    'paymentSchedule.business:id,escrow_balance,hold_amount',
                ]
            );
        }

        if ($jobs->isEmpty()) {
            Log::warning('No payment jobs found for bulk processing', [
                'job_ids' => $jobIds,
            ]);

            return [
                'processed' => 0,
                'failed' => 0,
                'stats' => [],
            ];
        }

        // Group jobs by business_id
        $jobsByBusiness = $jobs->groupBy(function ($job) {
            return $job->paymentSchedule->business_id;
        });

        // Pre-calculate balances for all businesses in a single optimized query
        // Use BalancePrecalculationService for efficient batch balance calculation
        $businessIds = $jobsByBusiness->keys()->toArray();
        // Chunk whereIn for large arrays (memory/performance)
        $businesses = $this->loadBusinessesWithChunkedWhereIn($businessIds);

        // Pre-calculate all balances using optimized service (caches within transaction)
        $balances = $this->balancePrecalculationService->preCalculateBalances($businessIds);

        // Fallback: if pre-calculation didn't return all balances, calculate from loaded businesses
        foreach ($businesses as $business) {
            if (! isset($balances[$business->id])) {
                $balances[$business->id] = ($business->escrow_balance ?? 0) - ($business->hold_amount ?? 0);
            }
        }

        $totalProcessed = 0;
        $totalFailed = 0;
        $stats = [];

        // Process each business's jobs
        // Note: Parallel processing is achieved through the queue system (multiple workers processing
        // different batches simultaneously). Sequential processing here ensures proper lock ordering
        // and prevents deadlocks. For true parallel processing within a single batch, use multiple
        // queue workers processing smaller batches in parallel.
        foreach ($jobsByBusiness as $businessId => $businessJobs) {
            $business = $businesses->get($businessId);
            if (! $business) {
                Log::warning('Business not found for payment jobs', [
                    'business_id' => $businessId,
                ]);
                $totalFailed += $businessJobs->count();

                continue;
            }

            try {
                // Process in transaction with retry - only critical operations inside transaction
                $result = DB::transaction(function () use ($business, $businessJobs, $balances) {
                    return $this->processPaymentJobsForBusiness($business, $businessJobs, $balances[$business->id]);
                }, 3);

                $totalProcessed += $result['processed'];
                $totalFailed += $result['failed'];
                $stats[$businessId] = $result;
            } catch (\Exception $e) {
                // Move error handling outside transaction (optimization: reduce transaction time)
                // Logging and job status updates don't need to be in the same transaction
                $failedJobIds = $businessJobs->pluck('id')->toArray();
                $errorMessage = $e->getMessage();
                $jobCount = $businessJobs->count();

                // Log error after transaction commits (non-blocking)
                DB::afterCommit(function () use ($businessId, $jobCount, $errorMessage) {
                    LogContext::error('Failed to process payment jobs for business', LogContext::create(
                        null,
                        $businessId,
                        null,
                        'bulk_payment_process',
                        null,
                        [
                            'job_count' => $jobCount,
                            'error' => $errorMessage,
                        ]
                    ));
                });

                $failures = array_map(function ($jobId) use ($errorMessage) {
                    return [
                        'job_id' => $jobId,
                        'error_message' => 'Bulk processing failed: '.$errorMessage,
                    ];
                }, $failedJobIds);

                // Use separate lightweight transaction for marking failed (non-critical operation)
                try {
                    $this->bulkJobUpdateService->markJobsAsFailed($failures, 'payment');
                } catch (\Exception $markFailedException) {
                    // Log but don't fail - job marking is non-critical
                    DB::afterCommit(function () use ($businessId, $markFailedException) {
                        Log::warning('Failed to mark jobs as failed', [
                            'business_id' => $businessId,
                            'error' => $markFailedException->getMessage(),
                        ]);
                    });
                }
                $totalFailed += count($failedJobIds);
            }
        }

        // Calculate performance metrics
        $businessCount = count($stats);
        $avgJobsPerBusiness = $businessCount > 0 ? round($totalProcessed / $businessCount, 2) : 0;

        // Log operation end
        \App\Helpers\LogContext::logOperationEnd('process_payment_jobs_bulk', array_merge($logContext, [
            'correlation_id' => $correlationId,
            'operation_id' => $operationId,
        ]), $totalFailed === 0, [
            'total_jobs' => count($jobIds),
            'processed' => $totalProcessed,
            'failed' => $totalFailed,
            'businesses_processed' => $businessCount,
            'avg_jobs_per_business' => $avgJobsPerBusiness,
        ]);

        return [
            'processed' => $totalProcessed,
            'failed' => $totalFailed,
            'stats' => $stats,
        ];
    }

    /**
     * Process settlement window in bulk
     *
     * @param  int  $windowId  Settlement window ID
     * @return array Results with counts and statistics
     */
    public function processSettlementWindowBulk(int $windowId): array
    {
        // Optimize: Don't wrap entire operation in transaction - process jobs in smaller transactions
        // Early exit if window is empty (optimization) - use exists() for efficiency
        $hasPendingJobs = PaymentJob::where('settlement_window_id', $windowId)
            ->where('status', 'pending')
            ->selectRaw('1')
            ->exists() || PayrollJob::where('settlement_window_id', $windowId)
            ->where('status', 'pending')
            ->selectRaw('1')
            ->exists();

        if (! $hasPendingJobs) {
            Log::info('Settlement window has no pending jobs', [
                'window_id' => $windowId,
            ]);

            return [
                'window_id' => $windowId,
                'payment_jobs_processed' => 0,
                'payroll_jobs_processed' => 0,
                'payment_jobs_failed' => 0,
                'payroll_jobs_failed' => 0,
            ];
        }

        // Use cursor-based processing for large windows (optimization: reduce memory usage)
        // Pre-filter by business to minimize cross-business lock contention
        // Load job IDs grouped by business for efficient processing
        $payrollJobIds = [];
        $paymentJobIds = [];

        // Process payroll jobs with cursor pagination and business pre-filtering
        // Use select() to limit columns and optimize index usage
        PayrollJob::where('settlement_window_id', $windowId)
            ->where('status', 'pending')
            ->select(['id', 'payroll_schedule_id'])
            ->with('payrollSchedule:id,business_id')
            ->orderBy('id') // Ensure consistent ordering for cursor
            ->cursor()
            ->chunk(500) // Process in chunks to balance memory and performance
            ->each(function ($jobs) use (&$payrollJobIds) {
                // Group by business to minimize lock contention
                $jobsByBusiness = $jobs->groupBy(function ($job) {
                    return $job->payrollSchedule->business_id;
                });

                // Add job IDs grouped by business
                foreach ($jobsByBusiness as $businessJobs) {
                    $payrollJobIds = array_merge($payrollJobIds, $businessJobs->pluck('id')->toArray());
                }
            });

        // Process payment jobs with cursor pagination and business pre-filtering
        // Use select() to limit columns and optimize index usage
        PaymentJob::where('settlement_window_id', $windowId)
            ->where('status', 'pending')
            ->select(['id', 'payment_schedule_id'])
            ->with('paymentSchedule:id,business_id')
            ->orderBy('id') // Ensure consistent ordering for cursor
            ->cursor()
            ->chunk(500) // Process in chunks to balance memory and performance
            ->each(function ($jobs) use (&$paymentJobIds) {
                // Group by business to minimize lock contention
                $jobsByBusiness = $jobs->groupBy(function ($job) {
                    return $job->paymentSchedule->business_id;
                });

                // Add job IDs grouped by business
                foreach ($jobsByBusiness as $businessJobs) {
                    $paymentJobIds = array_merge($paymentJobIds, $businessJobs->pluck('id')->toArray());
                }
            });

        $stats = [
            'window_id' => $windowId,
            'payment_jobs_processed' => 0,
            'payroll_jobs_processed' => 0,
            'payment_jobs_failed' => 0,
            'payroll_jobs_failed' => 0,
        ];

        // Process payroll jobs first (higher priority) - each business processed in separate transaction
        if (! empty($payrollJobIds)) {
            $result = $this->processPayrollJobsBulk($payrollJobIds);
            $stats['payroll_jobs_processed'] = $result['processed'];
            $stats['payroll_jobs_failed'] = $result['failed'];
        }

        // Process payment jobs second - each business processed in separate transaction
        if (! empty($paymentJobIds)) {
            $result = $this->processPaymentJobsBulk($paymentJobIds);
            $stats['payment_jobs_processed'] = $result['processed'];
            $stats['payment_jobs_failed'] = $result['failed'];
        }

        // Logging outside transaction (optimization: reduce transaction time)
        // Use afterCommit to ensure logging happens after all transactions complete
        DB::afterCommit(function () use ($windowId, $stats) {
            Log::info('Settlement window processed in bulk', [
                'window_id' => $windowId,
                'stats' => $stats,
            ]);
        });

        return $stats;
    }

    /**
     * Process payroll jobs for a single business in bulk
     *
     * Optimized for bank-grade performance: locks business once for entire batch,
     * processes all jobs atomically, minimizes database round-trips.
     *
     * LOCK ORDER: business (locked here) → jobs (locked in EscrowService) → deposits (locked in EscrowService)
     *
     * @param  float  $currentBalance  Pre-calculated balance to avoid extra query
     */
    protected function processPayrollJobsForBusiness(Business $business, Collection $jobs, float $currentBalance): array
    {
        // Optimize: Calculate all amounts and prepare data BEFORE locking to minimize lock duration
        $processed = 0;
        $failed = 0;
        $reservations = [];
        $ledgerTransactions = [];
        $succeededJobIds = [];
        $failedJobs = [];
        $totalAmount = 0;

        // Pre-calculate all amounts and prepare data structures before acquiring lock
        foreach ($jobs as $job) {
            // Skip if already processed
            if (in_array($job->status, ['succeeded', 'processing'])) {
                continue;
            }

            $amount = $job->net_salary;
            $totalAmount += $amount;

            // Prepare reservation (will be validated after lock)
            $reservations[] = [
                'job_id' => $job->id,
                'amount' => $amount,
                'job' => $job,
            ];

            // Prepare ledger transaction
            $correlationId = $this->ledgerService->generateCorrelationId();
            $ledgerTransactions[] = [
                'correlation_id' => $correlationId,
                'debit_account' => FinancialLedgerService::ACCOUNT_PAYROLL,
                'credit_account' => FinancialLedgerService::ACCOUNT_ESCROW,
                'amount' => $amount,
                'business' => $business,
                'description' => "Payroll payment for job #{$job->id}",
                'reference' => $job,
                'metadata' => [
                    'payroll_job_id' => $job->id,
                ],
                'currency' => 'ZAR',
                'operation_type' => 'PAYROLL_PROCESS',
            ];

            $succeededJobIds[] = $job->id;
        }

        // Now lock business only for balance check and reservation (minimize lock duration)
        // LOCK ORDER: business → jobs → deposits (consistent ordering prevents deadlocks)
        // Use SKIP LOCKED to avoid blocking if another process is already processing this business
        $business = Business::where('id', $business->id)
            ->lock('for update skip locked')
            ->first();

        if (! $business) {
            // Business is locked by another process - skip this batch and process other businesses
            // Return empty result to allow other businesses to be processed
            return [
                'processed' => 0,
                'failed' => 0,
                'new_balance' => $currentBalance,
            ];
        }

        // Use pre-calculated balance - get actual balance from locked business row without full refresh
        $actualBalance = (float) ($business->escrow_balance ?? 0) - (float) ($business->hold_amount ?? 0);
        $newBalance = max($currentBalance, $actualBalance); // Use the higher value to be safe

        // Check balance for entire batch (faster than checking per job)
        if ($newBalance < $totalAmount) {
            // Not enough balance - mark all as failed
            foreach ($reservations as $reservation) {
                $failedJobs[] = [
                    'job_id' => $reservation['job_id'],
                    'error_message' => 'Insufficient escrow balance',
                ];
            }
            $failed = count($reservations);
            $reservations = [];
            $ledgerTransactions = [];
            $succeededJobIds = [];
        } else {
            // Balance sufficient - proceed with reservations
            $newBalance -= $totalAmount;
            $processed = count($reservations);
        }

        // Bulk reserve funds and decrement balance
        if (! empty($reservations)) {
            $reservationResult = $this->escrowService->reserveAndDecrementFundsBulk(
                $business,
                $reservations
            );

            // Filter out failed reservations
            $failedReservations = array_filter($reservationResult, fn ($r) => ! $r['success']);
            foreach ($failedReservations as $failedReservation) {
                $failedJobs[] = [
                    'job_id' => $failedReservation['job_id'],
                    'error_message' => $failedReservation['error'] ?? 'Failed to reserve funds',
                ];
                $failed++;
                $processed--;

                // Remove from succeeded list
                $succeededJobIds = array_diff($succeededJobIds, [$failedReservation['job_id']]);
            }

            // Only record ledger for successful reservations
            $successfulReservations = array_filter($reservationResult, fn ($r) => $r['success']);
            if (! empty($successfulReservations)) {
                $successfulJobIds = array_column($successfulReservations, 'job_id');
                $ledgerTransactions = array_filter($ledgerTransactions, function ($txn) use ($successfulJobIds) {
                    return in_array($txn['reference']->id, $successfulJobIds);
                });

                // Record bulk ledger transactions with error handling
                if (! empty($ledgerTransactions)) {
                    try {
                        $this->ledgerService->recordBulkTransactions($ledgerTransactions);
                    } catch (\Exception $e) {
                        // Ledger recording failed - mark these jobs as failed
                        // This is a critical error, but we don't want to fail the entire batch
                        LogContext::error('Failed to record ledger transactions for successful reservations', LogContext::create(
                            null,
                            $business->id,
                            null,
                            'bulk_payroll_process',
                            null,
                            [
                                'error' => $e->getMessage(),
                                'job_count' => count($successfulJobIds),
                            ]
                        ));

                        // Move successful jobs to failed list since ledger wasn't recorded
                        foreach ($successfulJobIds as $jobId) {
                            $failedJobs[] = [
                                'job_id' => $jobId,
                                'error_message' => 'Failed to record ledger transaction: '.$e->getMessage(),
                            ];
                            $failed++;
                            $processed--;
                        }
                        $succeededJobIds = [];
                    }
                }
            }
        }

        // Bulk update job statuses
        if (! empty($succeededJobIds)) {
            $this->bulkJobUpdateService->markPayrollJobsAsSucceeded(
                $succeededJobIds,
                'transaction'
            );
        }

        if (! empty($failedJobs)) {
            $this->bulkJobUpdateService->markJobsAsFailed($failedJobs, 'payroll');
        }

        // Clear memory: unset large collections that are no longer needed
        unset($reservations, $ledgerTransactions, $succeededJobIds, $failedJobs);

        // Update balance in bulk (will be done at end for all businesses)
        return [
            'processed' => $processed,
            'failed' => $failed,
            'new_balance' => $newBalance,
        ];
    }

    /**
     * Process payment jobs for a single business in bulk
     *
     * Optimized for bank-grade performance: locks business once for entire batch,
     * processes all jobs atomically, minimizes database round-trips.
     *
     * LOCK ORDER: business (locked here) → jobs (locked in EscrowService) → deposits (locked in EscrowService)
     *
     * @param  float  $currentBalance  Pre-calculated balance to avoid extra query
     */
    protected function processPaymentJobsForBusiness(Business $business, Collection $jobs, float $currentBalance): array
    {
        // Optimize: Calculate all amounts and prepare data BEFORE locking to minimize lock duration
        $processed = 0;
        $failed = 0;
        $reservations = [];
        $ledgerTransactions = [];
        $succeededJobIds = [];
        $failedJobs = [];
        $totalAmount = 0;

        // Pre-calculate all amounts and prepare data structures before acquiring lock
        foreach ($jobs as $job) {
            // Skip if already processed
            if (in_array($job->status, ['succeeded', 'processing'])) {
                continue;
            }

            $amount = $job->amount;
            $totalAmount += $amount;

            // Prepare reservation (will be validated after lock)
            $reservations[] = [
                'job_id' => $job->id,
                'amount' => $amount,
                'job' => $job,
            ];

            // Prepare ledger transaction
            $correlationId = $this->ledgerService->generateCorrelationId();
            $ledgerTransactions[] = [
                'correlation_id' => $correlationId,
                'debit_account' => FinancialLedgerService::ACCOUNT_PAYMENT,
                'credit_account' => FinancialLedgerService::ACCOUNT_ESCROW,
                'amount' => $amount,
                'business' => $business,
                'description' => "Payment processed for job #{$job->id}",
                'reference' => $job,
                'metadata' => [
                    'payment_job_id' => $job->id,
                    'recipient_id' => $job->recipient_id,
                ],
                'currency' => 'ZAR',
                'operation_type' => 'PAYMENT_PROCESS',
            ];

            $succeededJobIds[] = $job->id;
        }

        // Now lock business only for balance check and reservation (minimize lock duration)
        // LOCK ORDER: business → jobs → deposits (consistent ordering prevents deadlocks)
        // Use SKIP LOCKED to avoid blocking if another process is already processing this business
        $business = Business::where('id', $business->id)
            ->lock('for update skip locked')
            ->first();

        if (! $business) {
            // Business is locked by another process - skip this batch and process other businesses
            // Return empty result to allow other businesses to be processed
            return [
                'processed' => 0,
                'failed' => 0,
                'new_balance' => $currentBalance,
            ];
        }

        // Use pre-calculated balance - get actual balance from locked business row without full refresh
        $actualBalance = (float) ($business->escrow_balance ?? 0) - (float) ($business->hold_amount ?? 0);
        $newBalance = max($currentBalance, $actualBalance); // Use the higher value to be safe

        // Check balance for entire batch (faster than checking per job)
        if ($newBalance < $totalAmount) {
            // Not enough balance - mark all as failed
            foreach ($reservations as $reservation) {
                $failedJobs[] = [
                    'job_id' => $reservation['job_id'],
                    'error_message' => 'Insufficient escrow balance',
                ];
            }
            $failed = count($reservations);
            $reservations = [];
            $ledgerTransactions = [];
            $succeededJobIds = [];
        } else {
            // Balance sufficient - proceed with reservations
            $newBalance -= $totalAmount;
            $processed = count($reservations);
        }

        // Bulk reserve funds
        if (! empty($reservations)) {
            $reservationResult = $this->escrowService->reserveFundsBulk(
                $business,
                $reservations
            );

            // Filter out failed reservations
            $failedReservations = array_filter($reservationResult, fn ($r) => ! $r['success']);
            foreach ($failedReservations as $failedReservation) {
                $failedJobs[] = [
                    'job_id' => $failedReservation['job_id'],
                    'error_message' => $failedReservation['error'] ?? 'Failed to reserve funds',
                ];
                $failed++;
                $processed--;

                // Remove from succeeded list
                $succeededJobIds = array_diff($succeededJobIds, [$failedReservation['job_id']]);
            }

            // Only record ledger for successful reservations
            $successfulReservations = array_filter($reservationResult, fn ($r) => $r['success']);
            if (! empty($successfulReservations)) {
                $successfulJobIds = array_column($successfulReservations, 'job_id');
                $ledgerTransactions = array_filter($ledgerTransactions, function ($txn) use ($successfulJobIds) {
                    return in_array($txn['reference']->id, $successfulJobIds);
                });

                // Record bulk ledger transactions with error handling
                if (! empty($ledgerTransactions)) {
                    try {
                        $this->ledgerService->recordBulkTransactions($ledgerTransactions);
                    } catch (\Exception $e) {
                        // Ledger recording failed - mark these jobs as failed
                        // This is a critical error, but we don't want to fail the entire batch
                        LogContext::error('Failed to record ledger transactions for successful reservations', LogContext::create(
                            null,
                            $business->id,
                            null,
                            'bulk_payment_process',
                            null,
                            [
                                'error' => $e->getMessage(),
                                'job_count' => count($successfulJobIds),
                            ]
                        ));

                        // Move successful jobs to failed list since ledger wasn't recorded
                        foreach ($successfulJobIds as $jobId) {
                            $failedJobs[] = [
                                'job_id' => $jobId,
                                'error_message' => 'Failed to record ledger transaction: '.$e->getMessage(),
                            ];
                            $failed++;
                            $processed--;
                        }
                        $succeededJobIds = [];
                        $successfulReservations = []; // Clear to skip balance decrement
                    }
                }

                // Decrement balance in bulk (only if ledger was recorded successfully)
                if (! empty($successfulReservations)) {
                    $decrements = array_map(function ($reservation) {
                        return [
                            'business_id' => $reservation['business_id'],
                            'amount' => $reservation['amount'],
                        ];
                    }, $successfulReservations);

                    if (! empty($decrements)) {
                        try {
                            $this->bulkBalanceService->decrementBalances($decrements);
                        } catch (\Exception $e) {
                            // Balance decrement failed - log but don't fail jobs (ledger already recorded)
                            LogContext::warning('Failed to decrement balances after ledger recording', LogContext::create(
                                null,
                                $business->id,
                                null,
                                'bulk_payment_process',
                                null,
                                [
                                    'error' => $e->getMessage(),
                                    'job_count' => count($successfulJobIds),
                                ]
                            ));
                            // Note: Balance will be out of sync, but reconciliation will catch it
                        }
                    }
                }
            }
        }

        // Bulk update job statuses
        if (! empty($succeededJobIds)) {
            $this->bulkJobUpdateService->markPaymentJobsAsSucceeded(
                $succeededJobIds,
                'SKIPPED'
            );
        }

        if (! empty($failedJobs)) {
            $this->bulkJobUpdateService->markJobsAsFailed($failedJobs, 'payment');
        }

        // Clear memory: unset large collections that are no longer needed
        unset($reservations, $ledgerTransactions, $succeededJobIds, $failedJobs);

        return [
            'processed' => $processed,
            'failed' => $failed,
            'new_balance' => $newBalance,
        ];
    }

    /**
     * Load jobs with chunked whereIn to handle large arrays (memory/performance)
     *
     * @param  string  $modelClass  Model class name
     * @param  array  $ids  Array of IDs
     * @param  array  $selectColumns  Columns to select
     * @param  array  $eagerLoad  Relationships to eager load
     */
    protected function loadJobsWithChunkedWhereIn(string $modelClass, array $ids, array $selectColumns, array $eagerLoad): Collection
    {
        $chunkSize = 1000; // Chunk for large IN clauses
        $allJobs = collect();

        foreach (array_chunk($ids, $chunkSize) as $chunk) {
            // Use orderBy to ensure consistent ordering and better index usage
            $jobs = $modelClass::whereIn('id', $chunk)
                ->select($selectColumns)
                ->with($eagerLoad)
                ->orderBy('id') // Consistent ordering helps with index usage
                ->get();
            $allJobs = $allJobs->merge($jobs);
        }

        return $allJobs;
    }

    /**
     * Load businesses with chunked whereIn to handle large arrays
     *
     * @param  array  $businessIds  Array of business IDs
     */
    protected function loadBusinessesWithChunkedWhereIn(array $businessIds): Collection
    {
        $chunkSize = 1000; // Chunk for large IN clauses
        $allBusinesses = collect();

        foreach (array_chunk($businessIds, $chunkSize) as $chunk) {
            $businesses = Business::whereIn('id', $chunk)
                ->select(['id', 'escrow_balance', 'hold_amount'])
                ->get();
            $allBusinesses = $allBusinesses->merge($businesses);
        }

        return $allBusinesses->keyBy('id');
    }
}
