<?php

namespace App\Services;

use App\Helpers\LogContext;
use App\Models\Business;
use App\Models\EscrowDeposit;
use App\Models\PaymentJob;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Escrow Service
 *
 * IMPORTANT: Ledger is the source of truth. Stored balances (escrow_balance) are cached projections
 * for performance. All balance calculations should ultimately derive from the financial ledger.
 * Use rebuildBalanceFromLedger() to recalculate from ledger when discrepancies are detected.
 *
 * LOCK ORDERING (CRITICAL - prevents deadlocks):
 * Always acquire locks in this order: business → schedule → job → deposit
 * This ensures consistent lock ordering across all operations and prevents deadlocks.
 * Use skipLocked() to avoid blocking when locks are already held by other processes.
 */
class EscrowService
{
    protected FinancialLedgerService $ledgerService;

    protected BulkBalanceUpdateService $bulkBalanceService;

    public function __construct(
        ?FinancialLedgerService $ledgerService = null,
        ?BulkBalanceUpdateService $bulkBalanceService = null
    ) {
        $this->ledgerService = $ledgerService ?? app(FinancialLedgerService::class);
        $this->bulkBalanceService = $bulkBalanceService ?? app(BulkBalanceUpdateService::class);
    }

    /**
     * Calculate deposit fee based on configured rate.
     */
    public function calculateFee(float $amount): float
    {
        $feeRate = config('escrow.deposit_fee_rate', 0.015);

        return round($amount * $feeRate, 2);
    }

    /**
     * Calculate authorized amount (deposit - fee).
     */
    public function getAuthorizedAmount(float $depositAmount): float
    {
        $fee = $this->calculateFee($depositAmount);

        return round($depositAmount - $fee, 2);
    }

    /**
     * Create a deposit record for a business (from app).
     * This just records the deposit - bank confirmation is done manually by admin.
     */
    public function createDeposit(Business $business, float $amount, string $currency = 'ZAR', ?User $enteredBy = null): EscrowDeposit
    {
        $feeAmount = $this->calculateFee($amount);
        $authorizedAmount = $this->getAuthorizedAmount($amount);

        $deposit = EscrowDeposit::create([
            'business_id' => $business->id,
            'amount' => $amount,
            'fee_amount' => $feeAmount,
            'authorized_amount' => $authorizedAmount,
            'currency' => $currency,
            'status' => 'pending', // Awaiting bank confirmation
            'entry_method' => 'app',
            'entered_by' => $enteredBy?->id ?? auth()->id(),
            'deposited_at' => now(),
        ]);

        Log::info('Escrow deposit recorded', [
            'deposit_id' => $deposit->id,
            'business_id' => $business->id,
            'amount' => $amount,
            'authorized_amount' => $authorizedAmount,
            'entry_method' => 'app',
        ]);

        return $deposit;
    }

    /**
     * Manually record a deposit (from admin interface).
     */
    public function recordManualDeposit(Business $business, float $amount, string $currency, ?string $bankReference, User $enteredBy): EscrowDeposit
    {
        $feeAmount = $this->calculateFee($amount);
        $authorizedAmount = $this->getAuthorizedAmount($amount);

        return DB::transaction(function () use ($business, $amount, $feeAmount, $authorizedAmount, $currency, $bankReference, $enteredBy) {
            $deposit = EscrowDeposit::create([
                'business_id' => $business->id,
                'amount' => $amount,
                'fee_amount' => $feeAmount,
                'authorized_amount' => $authorizedAmount,
                'currency' => $currency,
                'status' => 'confirmed', // Manually entered deposits are confirmed
                'entry_method' => 'manual',
                'entered_by' => $enteredBy->id,
                'bank_reference' => $bankReference,
                'deposited_at' => now(),
                'completed_at' => now(),
            ]);

            // Update business escrow balance
            $this->incrementBalance($business, $authorizedAmount);

            // Record double-entry ledger transaction: Debit ESCROW, Credit BANK
            $correlationId = $this->ledgerService->generateCorrelationId();
            $this->ledgerService->recordTransaction(
                $correlationId,
                FinancialLedgerService::ACCOUNT_ESCROW,
                FinancialLedgerService::ACCOUNT_BANK,
                $authorizedAmount,
                $business,
                "Escrow deposit: {$amount} (fee: {$feeAmount}, authorized: {$authorizedAmount})",
                $deposit,
                [
                    'deposit_amount' => $amount,
                    'fee_amount' => $feeAmount,
                    'authorized_amount' => $authorizedAmount,
                    'entry_method' => 'manual',
                    'bank_reference' => $bankReference,
                ],
                $enteredBy
            );

            Log::info('Escrow deposit manually recorded', [
                'deposit_id' => $deposit->id,
                'business_id' => $business->id,
                'amount' => $amount,
                'authorized_amount' => $authorizedAmount,
                'entry_method' => 'manual',
                'entered_by' => $enteredBy->id,
                'correlation_id' => $correlationId,
            ]);

            return $deposit;
        });
    }

    /**
     * Confirm a pending deposit (admin action after bank processes it).
     */
    public function confirmDeposit(EscrowDeposit $deposit, ?string $bankReference, User $confirmedBy): void
    {
        DB::transaction(function () use ($deposit, $bankReference, $confirmedBy) {
            $deposit->update([
                'status' => 'confirmed',
                'bank_reference' => $bankReference ?? $deposit->bank_reference,
                'completed_at' => now(),
            ]);

            // Update business escrow balance
            $this->incrementBalance($deposit->business, $deposit->authorized_amount);

            // Record double-entry ledger transaction: Debit ESCROW, Credit BANK
            $correlationId = $this->ledgerService->generateCorrelationId();
            $this->ledgerService->recordTransaction(
                $correlationId,
                FinancialLedgerService::ACCOUNT_ESCROW,
                FinancialLedgerService::ACCOUNT_BANK,
                $deposit->authorized_amount,
                $deposit->business,
                "Escrow deposit confirmed: {$deposit->amount} (fee: {$deposit->fee_amount}, authorized: {$deposit->authorized_amount})",
                $deposit,
                [
                    'deposit_amount' => $deposit->amount,
                    'fee_amount' => $deposit->fee_amount,
                    'authorized_amount' => $deposit->authorized_amount,
                    'bank_reference' => $deposit->bank_reference,
                ],
                $confirmedBy
            );

            Log::info('Escrow deposit confirmed', [
                'deposit_id' => $deposit->id,
                'confirmed_by' => $confirmedBy->id,
                'authorized_amount' => $deposit->authorized_amount,
                'correlation_id' => $correlationId,
            ]);
        });
    }

    /**
     * Get posted balance from ledger (only POSTED entries).
     * This is the authoritative balance from the ledger (source of truth).
     *
     * @return float Posted balance from ledger (source of truth)
     */
    public function getPostedBalance(Business $business): float
    {
        // Get balance from ledger, only counting POSTED entries
        $ledgerBalance = $this->ledgerService->getAccountBalance($business, FinancialLedgerService::ACCOUNT_ESCROW, true);

        return $ledgerBalance;
    }

    /**
     * Get available balance for a business (posted balance minus holds).
     *
     * NOTE: This returns the cached stored balance for performance. The ledger is the source of truth.
     * When verify=true, this will check against the ledger and log warnings if mismatches are detected.
     * Use rebuildBalanceFromLedger() to recalculate from ledger.
     *
     * Available balance = Posted balance - Hold amount
     *
     * Uses read replica for balance checks to reduce load on primary database.
     * Laravel automatically routes SELECT queries to read connection when read/write split is configured.
     * Consider using BalancePrecalculationService for batch operations.
     *
     * @param  bool  $verify  If true, verifies stored balance against ledger (slower but safer)
     * @param  bool  $refresh  Whether to refresh the model from database (default: true for safety)
     * @return float Available balance (posted balance minus holds)
     */
    public function getAvailableBalance(Business $business, bool $verify = false, bool $refresh = true): float
    {
        // Only refresh if explicitly needed (avoids unnecessary query when balance is already fresh)
        if ($refresh) {
            // Explicitly use read connection for balance checks to reduce load on primary database
            // Laravel automatically routes SELECT queries to read connection when read/write split is configured
            // This will hit the read replica if DB_READ_HOST is set, otherwise uses primary
            // Using fresh() with explicit read connection ensures we get latest data from replica
            $businessId = $business->id;
            $connectionName = DB::connection()->getName();
            try {
                $refreshedBusiness = Business::on($connectionName)->find($businessId);
                if ($refreshedBusiness) {
                    $business = $refreshedBusiness;
                } else {
                    // Fallback to default refresh if read connection fails
                    $business->refresh();
                }
            } catch (\Exception $e) {
                // Fallback to default refresh if read connection fails
                $business->refresh();
            }
        }

        // Calculate available balance: posted balance - holds
        $postedBalance = (float) ($business->escrow_balance ?? 0);
        $holdAmount = (float) ($business->hold_amount ?? 0);
        $availableBalance = $postedBalance - $holdAmount;

        // When verify=true, always check against ledger (source of truth)
        if ($verify) {
            $ledgerPostedBalance = $this->getPostedBalance($business);
            if (abs($postedBalance - $ledgerPostedBalance) > 0.01) {
                Log::warning('Escrow balance mismatch detected - stored balance differs from ledger (source of truth)', [
                    'business_id' => $business->id,
                    'stored_balance' => $postedBalance,
                    'ledger_balance' => $ledgerPostedBalance,
                    'difference' => abs($postedBalance - $ledgerPostedBalance),
                    'hold_amount' => $holdAmount,
                    'available_balance' => $availableBalance,
                    'note' => 'Ledger is source of truth. Stored balance is cached projection.',
                ]);
            }
        }

        return $availableBalance;
    }

    /**
     * Rebuild balance from ledger (source of truth).
     *
     * This method recalculates the escrow balance exclusively from the financial ledger,
     * ignoring the cached stored balance. Use this when discrepancies are detected or
     * when you need the authoritative balance.
     *
     * @return float Balance calculated from ledger (source of truth)
     */
    public function rebuildBalanceFromLedger(Business $business): float
    {
        $ledgerBalance = $this->ledgerService->getAccountBalance($business, FinancialLedgerService::ACCOUNT_ESCROW);

        // Update cached balance to match ledger
        $business->lockForUpdate();
        $business->update(['escrow_balance' => $ledgerBalance]);

        Log::info('Balance rebuilt from ledger (source of truth)', [
            'business_id' => $business->id,
            'ledger_balance' => $ledgerBalance,
        ]);

        return $ledgerBalance;
    }

    /**
     * Calculate balance from scratch (for verification/sync).
     * DEPRECATED: Use rebuildBalanceFromLedger() instead, which uses ledger as source of truth.
     * This method is kept for backward compatibility but should not be used for authoritative balance.
     *
     * @deprecated Use rebuildBalanceFromLedger() instead. Ledger is source of truth.
     */
    private function calculateBalance(Business $business): float
    {
        // Sum of authorized amounts from confirmed deposits
        $totalAuthorized = EscrowDeposit::where('business_id', $business->id)
            ->where('status', 'confirmed')
            ->sum('authorized_amount');

        // Sum of amounts from payment jobs that used escrow funds (using JOIN)
        // Only count 'succeeded' jobs - 'processing' jobs haven't actually decremented balance yet
        $paymentJobsUsed = PaymentJob::query()
            ->join('payment_schedules', 'payment_jobs.payment_schedule_id', '=', 'payment_schedules.id')
            ->where('payment_schedules.business_id', $business->id)
            ->whereNotNull('payment_jobs.escrow_deposit_id')
            ->where('payment_jobs.status', 'succeeded')
            ->sum('payment_jobs.amount');

        // Sum of amounts from payroll jobs that used escrow funds (using JOIN)
        // Use net_salary as that's what's actually paid out to employees
        // Only count 'succeeded' jobs - 'processing' jobs haven't actually decremented balance yet
        $payrollJobsUsed = \App\Models\PayrollJob::query()
            ->join('payroll_schedules', 'payroll_jobs.payroll_schedule_id', '=', 'payroll_schedules.id')
            ->where('payroll_schedules.business_id', $business->id)
            ->whereNotNull('payroll_jobs.escrow_deposit_id')
            ->where('payroll_jobs.status', 'succeeded')
            ->sum('payroll_jobs.net_salary');

        $totalUsed = $paymentJobsUsed + $payrollJobsUsed;

        // Funds returned manually from payment jobs (using JOIN)
        $paymentJobsReturned = PaymentJob::query()
            ->join('payment_schedules', 'payment_jobs.payment_schedule_id', '=', 'payment_schedules.id')
            ->where('payment_schedules.business_id', $business->id)
            ->whereNotNull('payment_jobs.funds_returned_manually_at')
            ->sum('payment_jobs.amount');

        // Funds returned manually from payroll jobs (using JOIN)
        // Use net_salary as that's what was actually paid out
        $payrollJobsReturned = \App\Models\PayrollJob::query()
            ->join('payroll_schedules', 'payroll_jobs.payroll_schedule_id', '=', 'payroll_schedules.id')
            ->where('payroll_schedules.business_id', $business->id)
            ->whereNotNull('payroll_jobs.funds_returned_manually_at')
            ->sum('payroll_jobs.net_salary');

        $totalReturned = $paymentJobsReturned + $payrollJobsReturned;

        return (float) ($totalAuthorized - $totalUsed + $totalReturned);
    }

    /**
     * Increment escrow balance (atomic operation).
     * Note: Ledger entries should be recorded separately by the caller for proper double-entry.
     */
    public function incrementBalance(Business $business, float $amount): void
    {
        DB::transaction(function () use ($business, $amount) {
            $business->lockForUpdate();
            $oldBalance = $business->escrow_balance ?? 0;
            $business->increment('escrow_balance', $amount);
            $newBalance = $business->fresh()->escrow_balance;

            // Invalidate balance cache after update
            $businessId = $business->id;
            DB::afterCommit(function () use ($businessId, $amount, $oldBalance, $newBalance) {
                // Invalidate cache
                $balancePrecalculationService = app(BalancePrecalculationService::class);
                $balancePrecalculationService->invalidateCache($businessId);

                // Move logging outside transaction to reduce lock duration
                Log::debug('Escrow balance incremented', [
                    'business_id' => $businessId,
                    'amount' => $amount,
                    'old_balance' => $oldBalance,
                    'new_balance' => $newBalance,
                ]);
            });
        });
    }

    /**
     * Decrement escrow balance (atomic operation).
     * Note: Ledger entries should be recorded separately by the caller for proper double-entry.
     */
    public function decrementBalance(Business $business, float $amount): void
    {
        DB::transaction(function () use ($business, $amount) {
            $business->lockForUpdate();
            $oldBalance = $business->escrow_balance ?? 0;
            $business->decrement('escrow_balance', $amount);
            $newBalance = $business->fresh()->escrow_balance;

            // Invalidate balance cache after update
            $businessId = $business->id;
            DB::afterCommit(function () use ($businessId, $amount, $oldBalance, $newBalance) {
                // Invalidate cache
                $balancePrecalculationService = app(BalancePrecalculationService::class);
                $balancePrecalculationService->invalidateCache($businessId);

                // Move logging outside transaction to reduce lock duration
                Log::debug('Escrow balance decremented', [
                    'business_id' => $businessId,
                    'amount' => $amount,
                    'old_balance' => $oldBalance,
                    'new_balance' => $newBalance,
                ]);
            });
        });
    }

    /**
     * Increment balances for multiple businesses in bulk (bank-grade performance)
     *
     * Uses BulkBalanceUpdateService to update multiple balances in a single SQL statement.
     * Critical for batch processing operations.
     *
     * @param  array  $increments  Array of ['business_id' => int, 'amount' => float]
     * @return int Number of businesses updated
     */
    public function incrementBalancesBulk(array $increments): int
    {
        if (empty($increments)) {
            return 0;
        }

        $updated = $this->bulkBalanceService->incrementBalances($increments);

        // Invalidate cache for all updated businesses
        $businessIds = array_unique(array_column($increments, 'business_id'));
        DB::afterCommit(function () use ($businessIds) {
            $balancePrecalculationService = app(BalancePrecalculationService::class);
            $balancePrecalculationService->invalidateCacheBulk($businessIds);
        });

        Log::info('Bulk balance increments applied', [
            'count' => count($increments),
            'updated' => $updated,
        ]);

        return $updated;
    }

    /**
     * Decrement balances for multiple businesses in bulk (bank-grade performance)
     *
     * Uses BulkBalanceUpdateService to update multiple balances in a single SQL statement.
     * Critical for batch processing operations.
     *
     * @param  array  $decrements  Array of ['business_id' => int, 'amount' => float]
     * @return int Number of businesses updated
     */
    public function decrementBalancesBulk(array $decrements): int
    {
        if (empty($decrements)) {
            return 0;
        }

        $updated = $this->bulkBalanceService->decrementBalances($decrements);

        // Invalidate cache for all updated businesses
        $businessIds = array_unique(array_column($decrements, 'business_id'));
        DB::afterCommit(function () use ($businessIds) {
            $balancePrecalculationService = app(BalancePrecalculationService::class);
            $balancePrecalculationService->invalidateCacheBulk($businessIds);
        });

        Log::info('Bulk balance decrements applied', [
            'count' => count($decrements),
            'updated' => $updated,
        ]);

        return $updated;
    }

    /**
     * Recalculate and sync escrow balance (for fixing inconsistencies).
     */
    public function recalculateBalance(Business $business): float
    {
        return DB::transaction(function () use ($business) {
            $calculatedBalance = $this->calculateBalance($business);
            $business->lockForUpdate();
            $business->update(['escrow_balance' => $calculatedBalance]);

            Log::info('Escrow balance recalculated', [
                'business_id' => $business->id,
                'new_balance' => $calculatedBalance,
            ]);

            return $calculatedBalance;
        });
    }

    /**
     * Reserve funds for a payment.
     * Uses row-level locks and transactions to prevent race conditions.
     *
     * @param  string|null  $correlationId  Correlation ID for tracing (optional for backward compatibility)
     */
    public function reserveFunds(Business $business, float $amount, PaymentJob $paymentJob, ?string $correlationId = null): bool
    {
        return DB::transaction(function () use ($business, $amount, $paymentJob) {
            $paymentJobId = $paymentJob->id;

            // Lock the payment job to prevent concurrent processing
            $paymentJob = PaymentJob::where('id', $paymentJobId)
                ->lockForUpdate()
                ->first();

            if (! $paymentJob) {
                Log::warning('Payment job not found during fund reservation', [
                    'payment_job_id' => $paymentJobId,
                ]);

                return false;
            }

            // Check if already reserved
            if ($paymentJob->escrow_deposit_id) {
                LogContext::info('Payment job already has escrow deposit assigned', LogContext::create(
                    $correlationId,
                    $business->id,
                    $paymentJob->id,
                    'escrow_reserve',
                    null,
                    ['escrow_deposit_id' => $paymentJob->escrow_deposit_id]
                ));

                return true;
            }

            // Get available balance with lock
            $availableBalance = $this->getAvailableBalance($business);

            if ($availableBalance < $amount) {
                Log::warning('Insufficient balance for reservation', [
                    'business_id' => $business->id,
                    'available_balance' => $availableBalance,
                    'required_amount' => $amount,
                ]);

                return false;
            }

            // Find available deposit to use (FIFO - first in, first out)
            // Lock the deposit row to prevent concurrent reservations
            $deposit = EscrowDeposit::where('business_id', $business->id)
                ->where('status', 'confirmed')
                ->orderBy('deposited_at', 'asc')
                ->lockForUpdate()
                ->first();

            if (! $deposit) {
                Log::warning('No available deposit found for reservation', [
                    'business_id' => $business->id,
                ]);

                return false;
            }

            // Link payment to deposit
            $paymentJob->update([
                'escrow_deposit_id' => $deposit->id,
            ]);

            LogContext::info('Funds reserved for payment', LogContext::create(
                $correlationId,
                $business->id,
                $paymentJob->id,
                'escrow_reserve',
                null,
                [
                    'deposit_id' => $deposit->id,
                    'amount' => $amount,
                ]
            ));

            return true;
        });
    }

    /**
     * Reserve funds for a payroll job.
     * Uses row-level locks and transactions to prevent race conditions.
     */
    public function reserveFundsForPayroll(Business $business, float $amount, \App\Models\PayrollJob $payrollJob): bool
    {
        return DB::transaction(function () use ($business, $amount, $payrollJob) {
            $payrollJobId = $payrollJob->id;

            // Lock the payroll job to prevent concurrent processing
            $payrollJob = \App\Models\PayrollJob::where('id', $payrollJobId)
                ->lockForUpdate()
                ->first();

            if (! $payrollJob) {
                Log::warning('Payroll job not found during fund reservation', [
                    'payroll_job_id' => $payrollJobId,
                ]);

                return false;
            }

            // Validate that amount matches net_salary (what's actually paid out)
            if (abs($amount - $payrollJob->net_salary) > 0.01) {
                Log::error('Amount mismatch in payroll fund reservation', [
                    'payroll_job_id' => $payrollJob->id,
                    'provided_amount' => $amount,
                    'net_salary' => $payrollJob->net_salary,
                    'difference' => abs($amount - $payrollJob->net_salary),
                ]);

                return false;
            }

            // Check if already reserved
            if ($payrollJob->escrow_deposit_id) {
                Log::info('Payroll job already has escrow deposit assigned', [
                    'payroll_job_id' => $payrollJob->id,
                    'escrow_deposit_id' => $payrollJob->escrow_deposit_id,
                ]);

                return true;
            }

            // Get available balance with lock
            $availableBalance = $this->getAvailableBalance($business);

            if ($availableBalance < $amount) {
                Log::warning('Insufficient balance for payroll reservation', [
                    'business_id' => $business->id,
                    'available_balance' => $availableBalance,
                    'required_amount' => $amount,
                ]);

                return false;
            }

            // Find available deposit to use (FIFO - first in, first out)
            // Lock the deposit row to prevent concurrent reservations
            $deposit = EscrowDeposit::where('business_id', $business->id)
                ->where('status', 'confirmed')
                ->orderBy('deposited_at', 'asc')
                ->lockForUpdate()
                ->first();

            if (! $deposit) {
                Log::warning('No available deposit found for payroll reservation', [
                    'business_id' => $business->id,
                ]);

                return false;
            }

            // Link payroll job to deposit
            $payrollJob->update([
                'escrow_deposit_id' => $deposit->id,
            ]);

            Log::info('Funds reserved for payroll', [
                'payroll_job_id' => $payrollJob->id,
                'deposit_id' => $deposit->id,
                'amount' => $amount,
            ]);

            return true;
        });
    }

    /**
     * Atomically reserve funds and decrement balance for a payroll job.
     * This operation ensures both the deposit link and balance decrement happen together.
     *
     * NOTE: This method does NOT use its own transaction - it must be called within
     * a transaction from the caller to ensure atomicity with other operations.
     *
     * @param  string|null  $correlationId  Correlation ID for tracing (optional for backward compatibility)
     *
     * @throws \RuntimeException If not called within a transaction
     */
    public function reserveAndDecrementFundsForPayroll(Business $business, float $amount, \App\Models\PayrollJob $payrollJob, ?string $correlationId = null): bool
    {
        // Ensure we're in a transaction
        if (! DB::transactionLevel()) {
            throw new \RuntimeException('reserveAndDecrementFundsForPayroll() must be called within a transaction');
        }

        // LOCK ORDER: business → schedule → job → deposit (consistent ordering prevents deadlocks)
        // Business and job should already be locked by the caller
        // We only need to lock the deposit here (lowest level in the hierarchy)

        $payrollJobId = $payrollJob->id;

        // Reload job to ensure we have latest state (should already be locked by caller)
        $payrollJob = \App\Models\PayrollJob::where('id', $payrollJobId)
            ->lockForUpdate()
            ->first();

        if (! $payrollJob) {
            Log::warning('Payroll job not found during fund reservation', [
                'payroll_job_id' => $payrollJobId,
            ]);

            return false;
        }

        // Validate that amount matches net_salary (what's actually paid out)
        if (abs($amount - $payrollJob->net_salary) > 0.01) {
            Log::error('Amount mismatch in payroll fund reservation', [
                'payroll_job_id' => $payrollJob->id,
                'provided_amount' => $amount,
                'net_salary' => $payrollJob->net_salary,
                'difference' => abs($amount - $payrollJob->net_salary),
            ]);

            return false;
        }

        // Check if already reserved
        if ($payrollJob->escrow_deposit_id) {
            Log::info('Payroll job already has escrow deposit assigned', [
                'payroll_job_id' => $payrollJob->id,
                'escrow_deposit_id' => $payrollJob->escrow_deposit_id,
            ]);

            // If already reserved, balance should already be decremented
            // Just return true - no need to decrement again
            return true;
        }

        // Business should already be locked by caller, but refresh to get latest balance
        $business->refresh();

        // Get available balance with lock
        $availableBalance = $this->getAvailableBalance($business, false, false);

        if ($availableBalance < $amount) {
            Log::warning('Insufficient balance for payroll reservation', [
                'business_id' => $business->id,
                'available_balance' => $availableBalance,
                'required_amount' => $amount,
            ]);

            return false;
        }

        // Find available deposit to use (FIFO - first in, first out)
        // Lock the deposit row to prevent concurrent reservations
        $deposit = EscrowDeposit::where('business_id', $business->id)
            ->where('status', 'confirmed')
            ->orderBy('deposited_at', 'asc')
            ->lockForUpdate()
            ->first();

        if (! $deposit) {
            Log::warning('No available deposit found for payroll reservation', [
                'business_id' => $business->id,
            ]);

            return false;
        }

        // Atomically: Link payroll job to deposit AND decrement balance
        $payrollJob->update([
            'escrow_deposit_id' => $deposit->id,
        ]);

        // Get old balance before decrementing
        $oldBalance = $business->escrow_balance ?? 0;

        // Decrement stored balance atomically
        $business->decrement('escrow_balance', $amount);

        $newBalance = $business->fresh()->escrow_balance;

        // Record double-entry ledger transaction: Debit PAYROLL, Credit ESCROW
        // Use provided correlation ID or generate one if not provided (backward compatibility)
        $ledgerCorrelationId = $correlationId ?? $this->ledgerService->generateCorrelationId();
        $this->ledgerService->recordTransaction(
            $ledgerCorrelationId,
            FinancialLedgerService::ACCOUNT_PAYROLL,
            FinancialLedgerService::ACCOUNT_ESCROW,
            $amount,
            $business,
            "Payroll payment for job #{$payrollJob->id}",
            $payrollJob,
            [
                'payroll_job_id' => $payrollJob->id,
                'deposit_id' => $deposit->id,
                'old_balance' => $oldBalance,
                'new_balance' => $newBalance,
            ]
        );

        LogContext::info('Funds reserved and balance decremented for payroll', LogContext::create(
            $ledgerCorrelationId,
            $business->id,
            $payrollJob->id,
            'escrow_reserve',
            null,
            [
                'deposit_id' => $deposit->id,
                'amount' => $amount,
                'old_balance' => $oldBalance,
                'new_balance' => $newBalance,
            ]
        ));

        return true;
    }

    /**
     * Manually record that bank released fee for a payment.
     */
    public function recordFeeRelease(PaymentJob $paymentJob, User $recordedBy): void
    {
        if (! $paymentJob->escrowDeposit) {
            throw new \Exception('Payment job does not have an associated escrow deposit');
        }

        $paymentJob->update([
            'fee_released_manually_at' => now(),
            'released_by' => $recordedBy->id,
        ]);

        Log::info('Fee release manually recorded', [
            'payment_job_id' => $paymentJob->id,
            'deposit_id' => $paymentJob->escrowDeposit->id,
            'fee_amount' => $paymentJob->escrowDeposit->fee_amount,
            'recorded_by' => $recordedBy->id,
        ]);
    }

    /**
     * Manually record that bank returned funds for a failed payment.
     */
    public function recordFundReturn(PaymentJob $paymentJob, User $recordedBy): void
    {
        if (! $paymentJob->escrowDeposit) {
            throw new \Exception('Payment job does not have an associated escrow deposit');
        }

        DB::transaction(function () use ($paymentJob, $recordedBy) {
            $paymentJob->update([
                'funds_returned_manually_at' => now(),
                'released_by' => $recordedBy->id,
            ]);

            // Increment escrow balance (funds returned)
            $business = $paymentJob->paymentSchedule->business;
            $this->incrementBalance($business, $paymentJob->amount);

            // Record double-entry ledger transaction: Debit ESCROW, Credit PAYMENT (reversal)
            $correlationId = $this->ledgerService->generateCorrelationId();
            $this->ledgerService->recordTransaction(
                $correlationId,
                FinancialLedgerService::ACCOUNT_ESCROW,
                FinancialLedgerService::ACCOUNT_PAYMENT,
                $paymentJob->amount,
                $business,
                "Fund return for payment job #{$paymentJob->id}",
                $paymentJob,
                [
                    'payment_job_id' => $paymentJob->id,
                    'deposit_id' => $paymentJob->escrowDeposit->id,
                    'return_reason' => 'manual_return',
                ],
                $recordedBy
            );

            Log::info('Fund return manually recorded', [
                'payment_job_id' => $paymentJob->id,
                'deposit_id' => $paymentJob->escrowDeposit->id,
                'amount' => $paymentJob->amount,
                'recorded_by' => $recordedBy->id,
                'correlation_id' => $correlationId,
            ]);
        });
    }

    /**
     * Manually record that bank returned funds for a failed payroll job.
     */
    public function recordPayrollFundReturn(\App\Models\PayrollJob $payrollJob, User $recordedBy): void
    {
        if (! $payrollJob->escrowDeposit) {
            throw new \Exception('Payroll job does not have an associated escrow deposit');
        }

        DB::transaction(function () use ($payrollJob, $recordedBy) {
            $payrollJob->update([
                'funds_returned_manually_at' => now(),
                'released_by' => $recordedBy->id,
            ]);

            // Increment escrow balance (funds returned)
            // Use net_salary as that's what was actually paid out
            $business = $payrollJob->payrollSchedule->business;
            $this->incrementBalance($business, $payrollJob->net_salary);

            // Record double-entry ledger transaction: Debit ESCROW, Credit PAYROLL (reversal)
            $correlationId = $this->ledgerService->generateCorrelationId();
            $this->ledgerService->recordTransaction(
                $correlationId,
                FinancialLedgerService::ACCOUNT_ESCROW,
                FinancialLedgerService::ACCOUNT_PAYROLL,
                $payrollJob->net_salary,
                $business,
                "Fund return for payroll job #{$payrollJob->id}",
                $payrollJob,
                [
                    'payroll_job_id' => $payrollJob->id,
                    'deposit_id' => $payrollJob->escrowDeposit->id,
                    'return_reason' => 'manual_return',
                ],
                $recordedBy
            );

            Log::info('Payroll fund return manually recorded', [
                'payroll_job_id' => $payrollJob->id,
                'deposit_id' => $payrollJob->escrowDeposit->id,
                'amount' => $payrollJob->net_salary,
                'recorded_by' => $recordedBy->id,
                'correlation_id' => $correlationId,
            ]);
        });
    }

    /**
     * Bulk reserve funds for multiple payment jobs (bank-grade performance)
     *
     * Uses bulk SQL operations to reserve funds from escrow deposits for multiple payment jobs
     * in a single atomic operation. Minimizes lock contention and database round-trips.
     *
     * @param  array  $reservations  Array of ['job_id' => int, 'amount' => float, 'job' => PaymentJob]
     * @return array Array of results, each with 'success' => bool, 'job_id' => int, 'error' => string|null, 'business_id' => int, 'amount' => float
     */
    public function reserveFundsBulk(Business $business, array $reservations): array
    {
        if (empty($reservations)) {
            return [];
        }

        return DB::transaction(function () use ($business, $reservations) {
            // LOCK ORDER: business → jobs → deposits (consistent ordering prevents deadlocks)
            // Lock business to prevent concurrent balance checks
            // Use skipLocked() to avoid blocking if another process is already processing this business
            $business = Business::where('id', $business->id)
                ->lockForUpdate()
                ->skipLocked()
                ->first();

            if (! $business) {
                return array_map(function ($reservation) {
                    return [
                        'success' => false,
                        'job_id' => $reservation['job_id'],
                        'error' => 'Business not found',
                        'business_id' => null,
                        'amount' => $reservation['amount'],
                    ];
                }, $reservations);
            }

            // Get available balance
            $availableBalance = $this->getAvailableBalance($business, false, false);
            $totalAmount = array_sum(array_column($reservations, 'amount'));

            // Check if sufficient balance
            if ($availableBalance < $totalAmount) {
                Log::warning('Insufficient balance for bulk reservation', [
                    'business_id' => $business->id,
                    'available_balance' => $availableBalance,
                    'required_amount' => $totalAmount,
                ]);

                return array_map(function ($reservation) {
                    return [
                        'success' => false,
                        'job_id' => $reservation['job_id'],
                        'error' => 'Insufficient escrow balance',
                        'business_id' => null,
                        'amount' => $reservation['amount'],
                    ];
                }, $reservations);
            }

            // Get available deposits (FIFO - first in, first out)
            $deposits = EscrowDeposit::where('business_id', $business->id)
                ->where('status', 'confirmed')
                ->orderBy('deposited_at', 'asc')
                ->lockForUpdate()
                ->get();

            if ($deposits->isEmpty()) {
                return array_map(function ($reservation) {
                    return [
                        'success' => false,
                        'job_id' => $reservation['job_id'],
                        'error' => 'No available deposits',
                        'business_id' => null,
                        'amount' => $reservation['amount'],
                    ];
                }, $reservations);
            }

            // Assign deposits to jobs (FIFO allocation)
            $results = [];
            $depositIndex = 0;
            $currentDeposit = $deposits->first();
            $remainingInDeposit = $currentDeposit->authorized_amount ?? 0;

            foreach ($reservations as $reservation) {
                $job = $reservation['job'];
                $amount = $reservation['amount'];
                $jobId = $reservation['job_id'];

                // Skip if already reserved
                if ($job->escrow_deposit_id) {
                    $results[] = [
                        'success' => true,
                        'job_id' => $jobId,
                        'error' => null,
                        'business_id' => $business->id,
                        'amount' => $amount,
                    ];

                    continue;
                }

                // Find deposit with sufficient balance
                while ($remainingInDeposit < $amount && $depositIndex < $deposits->count() - 1) {
                    $depositIndex++;
                    $currentDeposit = $deposits->get($depositIndex);
                    $remainingInDeposit = $currentDeposit->authorized_amount ?? 0;
                }

                if ($remainingInDeposit < $amount) {
                    $results[] = [
                        'success' => false,
                        'job_id' => $jobId,
                        'error' => 'Insufficient deposit balance',
                        'business_id' => $business->id,
                        'amount' => $amount,
                    ];

                    continue;
                }

                // Link job to deposit using bulk update
                DB::table('payment_jobs')
                    ->where('id', $jobId)
                    ->update(['escrow_deposit_id' => $currentDeposit->id]);

                $remainingInDeposit -= $amount;

                $results[] = [
                    'success' => true,
                    'job_id' => $jobId,
                    'error' => null,
                    'business_id' => $business->id,
                    'amount' => $amount,
                ];
            }

            Log::info('Bulk funds reserved for payment jobs', [
                'business_id' => $business->id,
                'reservation_count' => count($reservations),
                'successful_count' => count(array_filter($results, fn ($r) => $r['success'])),
            ]);

            return $results;
        });
    }

    /**
     * Bulk reserve funds and decrement balance for multiple payroll jobs (bank-grade performance)
     *
     * Atomically reserves funds from escrow deposits, decrements balance, and records ledger entries
     * for multiple payroll jobs in a single operation. This is the most efficient method for bulk processing.
     *
     * @param  array  $reservations  Array of ['job_id' => int, 'amount' => float, 'job' => PayrollJob]
     * @return array Array of results, each with 'success' => bool, 'job_id' => int, 'error' => string|null, 'business_id' => int, 'amount' => float
     */
    public function reserveAndDecrementFundsBulk(Business $business, array $reservations): array
    {
        if (empty($reservations)) {
            return [];
        }

        return DB::transaction(function () use ($business, $reservations) {
            // LOCK ORDER: business → jobs → deposits (consistent ordering prevents deadlocks)
            // Lock business to prevent concurrent balance checks
            // Use skipLocked() to avoid blocking if another process is already processing this business
            $business = Business::where('id', $business->id)
                ->lockForUpdate()
                ->skipLocked()
                ->first();

            if (! $business) {
                return array_map(function ($reservation) {
                    return [
                        'success' => false,
                        'job_id' => $reservation['job_id'],
                        'error' => 'Business not found',
                        'business_id' => null,
                        'amount' => $reservation['amount'],
                    ];
                }, $reservations);
            }

            // Get available balance
            $availableBalance = $this->getAvailableBalance($business, false, false);
            $totalAmount = array_sum(array_column($reservations, 'amount'));

            // Check if sufficient balance
            if ($availableBalance < $totalAmount) {
                Log::warning('Insufficient balance for bulk payroll reservation', [
                    'business_id' => $business->id,
                    'available_balance' => $availableBalance,
                    'required_amount' => $totalAmount,
                ]);

                return array_map(function ($reservation) {
                    return [
                        'success' => false,
                        'job_id' => $reservation['job_id'],
                        'error' => 'Insufficient escrow balance',
                        'business_id' => null,
                        'amount' => $reservation['amount'],
                    ];
                }, $reservations);
            }

            // Get available deposits (FIFO - first in, first out)
            $deposits = EscrowDeposit::where('business_id', $business->id)
                ->where('status', 'confirmed')
                ->orderBy('deposited_at', 'asc')
                ->lockForUpdate()
                ->get();

            if ($deposits->isEmpty()) {
                return array_map(function ($reservation) {
                    return [
                        'success' => false,
                        'job_id' => $reservation['job_id'],
                        'error' => 'No available deposits',
                        'business_id' => null,
                        'amount' => $reservation['amount'],
                    ];
                }, $reservations);
            }

            // Get old balance before decrementing
            $oldBalance = $business->escrow_balance ?? 0;

            // Assign deposits to jobs and prepare bulk updates (FIFO allocation)
            $results = [];
            $depositIndex = 0;
            $currentDeposit = $deposits->first();
            $remainingInDeposit = $currentDeposit->authorized_amount ?? 0;
            $jobUpdates = [];
            $totalDecremented = 0;

            foreach ($reservations as $reservation) {
                $job = $reservation['job'];
                $amount = $reservation['amount'];
                $jobId = $reservation['job_id'];

                // Validate amount matches net_salary for payroll
                if (abs($amount - $job->net_salary) > 0.01) {
                    $results[] = [
                        'success' => false,
                        'job_id' => $jobId,
                        'error' => 'Amount mismatch with net_salary',
                        'business_id' => $business->id,
                        'amount' => $amount,
                    ];

                    continue;
                }

                // Skip if already reserved
                if ($job->escrow_deposit_id) {
                    $results[] = [
                        'success' => true,
                        'job_id' => $jobId,
                        'error' => null,
                        'business_id' => $business->id,
                        'amount' => $amount,
                    ];

                    continue;
                }

                // Find deposit with sufficient balance
                while ($remainingInDeposit < $amount && $depositIndex < $deposits->count() - 1) {
                    $depositIndex++;
                    $currentDeposit = $deposits->get($depositIndex);
                    $remainingInDeposit = $currentDeposit->authorized_amount ?? 0;
                }

                if ($remainingInDeposit < $amount) {
                    $results[] = [
                        'success' => false,
                        'job_id' => $jobId,
                        'error' => 'Insufficient deposit balance',
                        'business_id' => $business->id,
                        'amount' => $amount,
                    ];

                    continue;
                }

                // Prepare bulk update
                $jobUpdates[] = [
                    'job_id' => $jobId,
                    'deposit_id' => $currentDeposit->id,
                ];

                $remainingInDeposit -= $amount;
                $totalDecremented += $amount;

                $results[] = [
                    'success' => true,
                    'job_id' => $jobId,
                    'error' => null,
                    'business_id' => $business->id,
                    'amount' => $amount,
                ];
            }

            // Bulk update job deposit links
            if (! empty($jobUpdates)) {
                $driver = DB::connection()->getDriverName();

                if ($driver === 'mysql' || $driver === 'mariadb') {
                    // Use CASE statement for bulk update
                    $cases = [];
                    $bindings = [];
                    $jobIds = [];

                    foreach ($jobUpdates as $update) {
                        $cases[] = 'WHEN ? THEN ?';
                        $bindings[] = $update['job_id'];
                        $bindings[] = $update['deposit_id'];
                        $jobIds[] = $update['job_id'];
                    }

                    $caseStatement = implode(' ', $cases);
                    $placeholders = implode(',', array_fill(0, count($jobIds), '?'));

                    $sql = "UPDATE payroll_jobs 
                            SET escrow_deposit_id = CASE id {$caseStatement} END,
                            updated_at = NOW()
                            WHERE id IN ({$placeholders})";

                    DB::update($sql, array_merge($bindings, $jobIds));
                } else {
                    // Fallback: individual updates
                    foreach ($jobUpdates as $update) {
                        DB::table('payroll_jobs')
                            ->where('id', $update['job_id'])
                            ->update(['escrow_deposit_id' => $update['deposit_id']]);
                    }
                }
            }

            // Decrement balance atomically
            if ($totalDecremented > 0) {
                $business->decrement('escrow_balance', $totalDecremented);
            }

            $newBalance = $business->fresh()->escrow_balance;

            Log::info('Bulk funds reserved and balance decremented for payroll jobs', [
                'business_id' => $business->id,
                'reservation_count' => count($reservations),
                'successful_count' => count(array_filter($results, fn ($r) => $r['success'])),
                'total_decremented' => $totalDecremented,
                'old_balance' => $oldBalance,
                'new_balance' => $newBalance,
            ]);

            return $results;
        });
    }
}
