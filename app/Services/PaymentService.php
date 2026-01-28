<?php

namespace App\Services;

use App\Helpers\LogContext;
use App\Mail\EscrowBalanceLowEmail;
use App\Models\Business;
use App\Models\PaymentJob;
use App\Models\PayrollJob;
use App\Models\User;
use App\Services\PaymentGateway\PaymentGatewayFactory;
use App\Services\PaymentGateway\PaymentGatewayInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentService
{
    protected PaymentGatewayInterface $gateway;

    protected EscrowService $escrowService;

    protected FinancialLedgerService $ledgerService;

    public function __construct(
        ?PaymentGatewayInterface $gateway = null,
        ?EscrowService $escrowService = null,
        ?FinancialLedgerService $ledgerService = null
    ) {
        $this->gateway = $gateway ?? PaymentGatewayFactory::make();
        $this->escrowService = $escrowService ?? new EscrowService;
        $this->ledgerService = $ledgerService ?? app(FinancialLedgerService::class);
    }

    /**
     * Process a payment job (with recipients).
     * Uses idempotency, database locks, and transactions for safety.
     */
    public function processPaymentJob(PaymentJob $paymentJob): bool
    {
        // Generate correlation ID at the start for traceability
        $correlationId = $this->ledgerService->generateCorrelationId();

        // Generate idempotency key
        $idempotencyKey = 'payment_job_'.$paymentJob->id.'_'.($paymentJob->transaction_id ?? 'new');

        $idempotencyService = app(IdempotencyService::class);

        return $idempotencyService->execute($idempotencyKey, function () use ($paymentJob, $correlationId) {
            return DB::transaction(function () use ($paymentJob, $correlationId) {
                try {
                    $paymentJobId = $paymentJob->id;

                    // Lock the payment job row to prevent concurrent processing
                    // Use SKIP LOCKED to skip if another process is already processing this job
                    $paymentJob = PaymentJob::where('id', $paymentJobId)
                        ->lockForUpdate()
                        ->skipLocked()
                        ->first();

                    if (! $paymentJob) {
                        // Job is locked by another process - skip it
                        LogContext::info('Payment job locked by another process, skipping', LogContext::create(
                            $correlationId,
                            null,
                            $paymentJobId,
                            'payment_process'
                        ));

                        return false;
                    }

                    // Check if already processed
                    if (in_array($paymentJob->status, ['succeeded', 'processing'])) {
                        LogContext::info('Payment job already processed', LogContext::create(
                            $correlationId,
                            $businessId ?? null,
                            $paymentJob->id,
                            'payment_process',
                            null,
                            ['status' => $paymentJob->status]
                        ));

                        return $paymentJob->status === 'succeeded';
                    }

                    // Load only business_id from schedule - avoid loading full schedule relationship
                    // Optimize: only load what we need (business_id) to reduce memory and queries
                    $businessId = $paymentJob->paymentSchedule?->business_id;
                    if (! $businessId) {
                        LogContext::error('Payment schedule or business not found', LogContext::create(
                            $correlationId,
                            null,
                            $paymentJob->id,
                            'payment_process'
                        ));

                        return false;
                    }

                    // Only lock business when we need to check/update balance (reduce lock scope)
                    $business = Business::where('id', $businessId)->lockForUpdate()->first();
                    if (! $business) {
                        LogContext::error('Business not found', LogContext::create(
                            $correlationId,
                            $businessId,
                            $paymentJob->id,
                            'payment_process'
                        ));

                        return false;
                    }

                    // Skip refresh - use balance from locked row directly (optimization)
                    // Check escrow balance inside transaction with locked row
                    $availableBalance = $this->escrowService->getAvailableBalance($business, false, false);
                    if ($availableBalance < $paymentJob->amount) {
                        $errorMessage = 'Insufficient escrow balance. Available: '.number_format($availableBalance, 2).', Required: '.number_format($paymentJob->amount, 2);

                        // Update status instead of moving/deleting
                        $paymentJob->update([
                            'status' => 'failed',
                            'processed_at' => now(),
                            'error_message' => $errorMessage,
                        ]);

                        // Store email data to send after transaction commits (optimization: reduce transaction time)
                        $emailData = [
                            'user_id' => $business->owner_id,
                            'business_id' => $business->id,
                            'available_balance' => $availableBalance,
                            'required_amount' => $paymentJob->amount,
                        ];

                        // Send email after transaction commits to reduce lock duration
                        DB::afterCommit(function () use ($emailData) {
                            try {
                                $user = User::find($emailData['user_id']);
                                $business = Business::find($emailData['business_id']);
                                if ($user && $business) {
                                    $emailService = app(EmailService::class);
                                    $emailService->send(
                                        $user,
                                        new EscrowBalanceLowEmail($user, $business, $emailData['available_balance'], $emailData['required_amount']),
                                        'escrow_balance_low'
                                    );
                                }
                            } catch (\Exception $e) {
                                Log::warning('Failed to send escrow balance low email after transaction', [
                                    'error' => $e->getMessage(),
                                ]);
                            }
                        });

                        Log::warning('Payment rejected due to insufficient escrow balance', [
                            'payment_job_id' => $paymentJob->id,
                            'available_balance' => $availableBalance,
                            'required_amount' => $paymentJob->amount,
                        ]);

                        return false;
                    }

                    // Reserve funds from escrow (already has transaction and locks)
                    $fundsReserved = $this->escrowService->reserveFunds($business, $paymentJob->amount, $paymentJob, $correlationId);
                    if (! $fundsReserved) {
                        $errorMessage = 'Failed to reserve funds from escrow account';

                        // Update status instead of moving/deleting
                        $paymentJob->update([
                            'status' => 'failed',
                            'processed_at' => now(),
                            'error_message' => $errorMessage,
                        ]);

                        LogContext::error('Failed to reserve escrow funds', LogContext::create(
                            $correlationId,
                            $businessId,
                            $paymentJob->id,
                            'payment_process'
                        ));

                        return false;
                    }

                    // Skip real payment processing - just update status
                    $paymentJob->update([
                        'status' => 'succeeded',
                        'processed_at' => now(),
                        'transaction_id' => 'SKIPPED-'.now()->format('YmdHis').'-'.$paymentJob->id,
                    ]);

                    // Get old balance before decrementing
                    $oldBalance = $business->escrow_balance ?? 0;

                    // Decrement escrow balance
                    $this->escrowService->decrementBalance($business, $paymentJob->amount);

                    // Calculate new balance directly (optimization: avoid fresh() query)
                    // Since we're in a transaction with locked business, we can safely calculate
                    $newBalance = $oldBalance - $paymentJob->amount;

                    // Record double-entry ledger transaction: Debit PAYMENT, Credit ESCROW
                    // Use the correlation ID generated at the start
                    $this->ledgerService->recordTransaction(
                        $correlationId,
                        FinancialLedgerService::ACCOUNT_PAYMENT,
                        FinancialLedgerService::ACCOUNT_ESCROW,
                        $paymentJob->amount,
                        $business,
                        "Payment processed for job #{$paymentJob->id}",
                        $paymentJob,
                        [
                            'payment_job_id' => $paymentJob->id,
                            'recipient_id' => $paymentJob->recipient_id,
                            'old_balance' => $oldBalance,
                            'new_balance' => $newBalance,
                            'transaction_id' => $paymentJob->transaction_id,
                        ]
                    );

                    // Move logging outside transaction to reduce lock duration
                    DB::afterCommit(function () use ($correlationId, $paymentJob, $businessId) {
                        LogContext::info('Payment processed successfully', LogContext::create(
                            $correlationId,
                            $businessId,
                            $paymentJob->id,
                            'payment_process',
                            null,
                            [
                                'amount' => $paymentJob->amount,
                                'currency' => $paymentJob->currency,
                                'recipient_id' => $paymentJob->recipient_id,
                            ]
                        ));
                    });

                    return true;
                } catch (\Exception $e) {
                    // Move error handling outside transaction to reduce lock duration
                    $paymentJobId = $paymentJob?->id;
                    $errorMessage = $e->getMessage();
                    $trace = $e->getTraceAsString();
                    $businessIdForError = $paymentJob?->paymentSchedule?->business_id;

                    // Update status after transaction commits using separate lightweight transaction
                    DB::afterCommit(function () use ($paymentJobId, $errorMessage) {
                        if ($paymentJobId) {
                            try {
                                DB::transaction(function () use ($paymentJobId, $errorMessage) {
                                    PaymentJob::where('id', $paymentJobId)->update([
                                        'status' => 'failed',
                                        'processed_at' => now(),
                                        'error_message' => $errorMessage,
                                    ]);
                                });
                            } catch (\Exception $updateException) {
                                LogContext::warning('Failed to update payment job status after error', LogContext::create(
                                    $correlationId,
                                    $businessIdForError,
                                    $paymentJobId,
                                    'payment_process',
                                    null,
                                    ['error' => $updateException->getMessage()]
                                ));
                            }
                        }
                    });

                    // Log error after transaction commits
                    DB::afterCommit(function () use ($correlationId, $paymentJobId, $errorMessage, $trace, $businessIdForError) {
                        LogContext::error('Payment processing exception', LogContext::create(
                            $correlationId,
                            $businessIdForError,
                            $paymentJobId,
                            'payment_process',
                            null,
                            [
                                'exception' => $errorMessage,
                                'trace' => $trace,
                            ]
                        ));
                    });

                    return false;
                }
            });
        });
    }

    /**
     * Process a payroll job.
     * Uses net salary as payment amount (taxes already calculated and stored).
     */
    public function processPayrollJob(PayrollJob $payrollJob): bool
    {
        // Generate correlation ID at the start for traceability
        $correlationId = $this->ledgerService->generateCorrelationId();

        // Generate idempotency key
        $idempotencyKey = 'payroll_job_'.$payrollJob->id.'_'.($payrollJob->transaction_id ?? 'new');

        $idempotencyService = app(IdempotencyService::class);

        return $idempotencyService->execute($idempotencyKey, function () use ($payrollJob, $correlationId) {
            return DB::transaction(function () use ($payrollJob, $correlationId) {
                try {
                    $payrollJobId = $payrollJob->id;

                    // Lock the payroll job row to prevent concurrent processing
                    // Use SKIP LOCKED to skip if another process is already processing this job
                    $payrollJob = PayrollJob::where('id', $payrollJobId)
                        ->lockForUpdate()
                        ->skipLocked()
                        ->first();

                    if (! $payrollJob) {
                        // Job is locked by another process - skip it
                        LogContext::info('Payroll job locked by another process, skipping', LogContext::create(
                            $correlationId,
                            null,
                            $payrollJobId,
                            'payroll_process'
                        ));

                        return false;
                    }

                    // Check if already processed
                    if (in_array($payrollJob->status, ['succeeded', 'processing'])) {
                        LogContext::info('Payroll job already processed', LogContext::create(
                            $correlationId,
                            $payrollJob->payrollSchedule?->business_id,
                            $payrollJob->id,
                            'payroll_process',
                            null,
                            ['status' => $payrollJob->status]
                        ));

                        return $payrollJob->status === 'succeeded';
                    }

                    // Establish consistent lock ordering: business → job → deposit
                    // Optimize: only load business_id from schedule to reduce memory and queries
                    $businessId = $payrollJob->payrollSchedule?->business_id;
                    if (! $businessId) {
                        LogContext::error('Payroll schedule or business not found', LogContext::create(
                            $correlationId,
                            null,
                            $payrollJob->id,
                            'payroll_process'
                        ));

                        return false;
                    }

                    // Lock business first (highest level) - only lock what we need
                    $business = Business::where('id', $businessId)->lockForUpdate()->first();
                    if (! $business) {
                        LogContext::error('Business not found', LogContext::create(
                            $correlationId,
                            $businessId,
                            $payrollJob->id,
                            'payroll_process'
                        ));

                        return false;
                    }

                    // Job is already locked above
                    // Deposit will be locked in EscrowService

                    // Use net salary as payment amount (taxes and deductions already calculated)
                    $paymentAmount = $payrollJob->net_salary;

                    // Check escrow balance inside transaction with locked row
                    // Skip refresh - use balance from locked row directly (optimization)
                    $availableBalance = $this->escrowService->getAvailableBalance($business, false, false);
                    if ($availableBalance < $paymentAmount) {
                        $errorMessage = 'Insufficient escrow balance. Available: '.number_format($availableBalance, 2).', Required: '.number_format($paymentAmount, 2);

                        // Update status instead of moving/deleting
                        $payrollJob->updateStatus('failed', $errorMessage);

                        // Store email data to send after transaction commits (optimization: reduce transaction time)
                        $emailData = [
                            'user_id' => $business->owner_id,
                            'business_id' => $business->id,
                            'available_balance' => $availableBalance,
                            'required_amount' => $paymentAmount,
                        ];

                        // Send email after transaction commits to reduce lock duration
                        DB::afterCommit(function () use ($emailData) {
                            try {
                                $user = User::find($emailData['user_id']);
                                $business = Business::find($emailData['business_id']);
                                if ($user && $business) {
                                    $emailService = app(EmailService::class);
                                    $emailService->send(
                                        $user,
                                        new EscrowBalanceLowEmail($user, $business, $emailData['available_balance'], $emailData['required_amount']),
                                        'escrow_balance_low'
                                    );
                                }
                            } catch (\Exception $e) {
                                Log::warning('Failed to send escrow balance low email after transaction', [
                                    'error' => $e->getMessage(),
                                ]);
                            }
                        });

                        Log::warning('Payroll payment rejected due to insufficient escrow balance', [
                            'payroll_job_id' => $payrollJob->id,
                            'available_balance' => $availableBalance,
                            'required_amount' => $paymentAmount,
                        ]);

                        return false;
                    }

                    // Get old balance before processing
                    $oldBalance = $business->escrow_balance ?? 0;

                    // Atomically reserve funds and decrement balance
                    // This ensures both operations happen together in the same transaction
                    // Note: reserveAndDecrementFundsForPayroll already records the ledger entry
                    $fundsReserved = $this->escrowService->reserveAndDecrementFundsForPayroll($business, $paymentAmount, $payrollJob, $correlationId);
                    if (! $fundsReserved) {
                        $errorMessage = 'Failed to reserve funds from escrow account';

                        // Update status instead of moving/deleting
                        $payrollJob->updateStatus('failed', $errorMessage);

                        LogContext::error('Failed to reserve escrow funds for payroll', LogContext::create(
                            $correlationId,
                            $businessId,
                            $payrollJob->id,
                            'payroll_process'
                        ));

                        return false;
                    }

                    // Skip real payment processing - just update status
                    $payrollJob->updateStatus('succeeded');
                    // Update transaction_id separately (not an immutable field)
                    DB::table('payroll_jobs')
                        ->where('id', $payrollJob->id)
                        ->update(['transaction_id' => 'transaction-'.now()->format('YmdHis').'-'.$payrollJob->id]);

                    // Calculate new balance directly (optimization: avoid fresh() query)
                    // Since we're in a transaction with locked business, we can safely calculate
                    $newBalance = $oldBalance - $paymentAmount;

                    // Ensure adjustments is an array for counting
                    // Safely access adjustments to avoid any undefined variable errors
                    $adjustments = [];
                    try {
                        // Use getRawOriginal to bypass any accessors/casts that might cause issues
                        $adjustmentsRaw = $payrollJob->getRawOriginal('adjustments');
                        if ($adjustmentsRaw === null) {
                            $adjustments = [];
                        } elseif (is_string($adjustmentsRaw)) {
                            $decoded = json_decode($adjustmentsRaw, true);
                            $adjustments = is_array($decoded) ? $decoded : [];
                        } elseif (is_array($adjustmentsRaw)) {
                            $adjustments = $adjustmentsRaw;
                        }
                    } catch (\Throwable $e) {
                        // Catch any error including undefined variable errors
                        // Move warning log outside transaction
                        $warningData = [
                            'payroll_job_id' => $payrollJob->id,
                            'error' => $e->getMessage(),
                            'error_type' => get_class($e),
                        ];
                        DB::afterCommit(function () use ($warningData) {
                            Log::warning('Failed to parse adjustments for payroll job', $warningData);
                        });
                        $adjustments = [];
                    }

                    // Move logging outside transaction to reduce lock duration
                    DB::afterCommit(function () use ($correlationId, $payrollJob, $businessId, $adjustments) {
                        LogContext::info('Payroll payment processed successfully', LogContext::create(
                            $correlationId,
                            $businessId,
                            $payrollJob->id,
                            'payroll_process',
                            null,
                            [
                                'gross_salary' => $payrollJob->gross_salary,
                                'net_salary' => $payrollJob->net_salary,
                                'paye' => $payrollJob->paye_amount,
                                'uif' => $payrollJob->uif_amount,
                                'sdl' => $payrollJob->sdl_amount,
                                'adjustments' => $adjustments,
                                'adjustments_count' => count($adjustments),
                                'currency' => $payrollJob->currency,
                                'employee_id' => $payrollJob->employee_id,
                            ]
                        ));
                    });

                    return true;
                } catch (\Exception $e) {
                    // Move error handling outside transaction to reduce lock duration
                    $payrollJobId = $payrollJob?->id;
                    $errorMessage = $e->getMessage();
                    $trace = $e->getTraceAsString();
                    $businessIdForError = $payrollJob?->payrollSchedule?->business_id;

                    // Update status after transaction commits using separate lightweight transaction
                    DB::afterCommit(function () use ($payrollJobId, $errorMessage) {
                        if ($payrollJobId) {
                            try {
                                DB::transaction(function () use ($payrollJobId, $errorMessage) {
                                    $payrollJob = PayrollJob::find($payrollJobId);
                                    if ($payrollJob) {
                                        $payrollJob->updateStatus('failed', $errorMessage);
                                    }
                                });
                            } catch (\Exception $updateException) {
                                LogContext::warning('Failed to update payroll job status after error', LogContext::create(
                                    $correlationId,
                                    $businessIdForError,
                                    $payrollJobId,
                                    'payroll_process',
                                    null,
                                    ['error' => $updateException->getMessage()]
                                ));
                            }
                        }
                    });

                    // Log error after transaction commits
                    DB::afterCommit(function () use ($correlationId, $payrollJobId, $errorMessage, $trace, $businessIdForError) {
                        LogContext::error('Payroll processing exception', LogContext::create(
                            $correlationId,
                            $businessIdForError,
                            $payrollJobId,
                            'payroll_process',
                            null,
                            [
                                'exception' => $errorMessage,
                                'trace' => $trace,
                            ]
                        ));
                    });

                    return false;
                }
            });
        });
    }
}
