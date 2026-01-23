<?php

namespace App\Services;

use App\Models\Business;
use App\Models\EscrowDeposit;
use App\Models\PaymentJob;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EscrowService
{
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

            Log::info('Escrow deposit manually recorded', [
                'deposit_id' => $deposit->id,
                'business_id' => $business->id,
                'amount' => $amount,
                'authorized_amount' => $authorizedAmount,
                'entry_method' => 'manual',
                'entered_by' => $enteredBy->id,
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

            Log::info('Escrow deposit confirmed', [
                'deposit_id' => $deposit->id,
                'confirmed_by' => $confirmedBy->id,
                'authorized_amount' => $deposit->authorized_amount,
            ]);
        });
    }

    /**
     * Get available balance for a business.
     * Returns stored balance for performance, with optional verification.
     *
     * @param  bool  $refresh  Whether to refresh the model from database (default: true for safety)
     */
    public function getAvailableBalance(Business $business, bool $verify = false, bool $refresh = true): float
    {
        // Only refresh if explicitly needed (avoids unnecessary query when balance is already fresh)
        if ($refresh) {
            $business->refresh();
        }

        $storedBalance = (float) ($business->escrow_balance ?? 0);

        // Optional verification (for debugging/audit)
        if ($verify) {
            $calculatedBalance = $this->calculateBalance($business);
            if (abs($storedBalance - $calculatedBalance) > 0.01) {
                Log::warning('Escrow balance mismatch detected', [
                    'business_id' => $business->id,
                    'stored_balance' => $storedBalance,
                    'calculated_balance' => $calculatedBalance,
                    'difference' => abs($storedBalance - $calculatedBalance),
                ]);
            }
        }

        return $storedBalance;
    }

    /**
     * Calculate balance from scratch (for verification/sync).
     * Optimized with JOINs instead of whereHas subqueries.
     */
    private function calculateBalance(Business $business): float
    {
        // Sum of authorized amounts from confirmed deposits
        $totalAuthorized = EscrowDeposit::where('business_id', $business->id)
            ->where('status', 'confirmed')
            ->sum('authorized_amount');

        // Sum of amounts from payment jobs that used escrow funds (using JOIN)
        $paymentJobsUsed = PaymentJob::query()
            ->join('payment_schedules', 'payment_jobs.payment_schedule_id', '=', 'payment_schedules.id')
            ->where('payment_schedules.business_id', $business->id)
            ->whereNotNull('payment_jobs.escrow_deposit_id')
            ->whereIn('payment_jobs.status', ['succeeded', 'processing'])
            ->sum('payment_jobs.amount');

        // Sum of amounts from payroll jobs that used escrow funds (using JOIN)
        $payrollJobsUsed = \App\Models\PayrollJob::query()
            ->join('payroll_schedules', 'payroll_jobs.payroll_schedule_id', '=', 'payroll_schedules.id')
            ->where('payroll_schedules.business_id', $business->id)
            ->whereNotNull('payroll_jobs.escrow_deposit_id')
            ->whereIn('payroll_jobs.status', ['succeeded', 'processing'])
            ->sum('payroll_jobs.gross_salary');

        $totalUsed = $paymentJobsUsed + $payrollJobsUsed;

        // Funds returned manually from payment jobs (using JOIN)
        $paymentJobsReturned = PaymentJob::query()
            ->join('payment_schedules', 'payment_jobs.payment_schedule_id', '=', 'payment_schedules.id')
            ->where('payment_schedules.business_id', $business->id)
            ->whereNotNull('payment_jobs.funds_returned_manually_at')
            ->sum('payment_jobs.amount');

        // Funds returned manually from payroll jobs (using JOIN)
        $payrollJobsReturned = \App\Models\PayrollJob::query()
            ->join('payroll_schedules', 'payroll_jobs.payroll_schedule_id', '=', 'payroll_schedules.id')
            ->where('payroll_schedules.business_id', $business->id)
            ->whereNotNull('payroll_jobs.funds_returned_manually_at')
            ->sum('payroll_jobs.gross_salary');

        $totalReturned = $paymentJobsReturned + $payrollJobsReturned;

        return (float) ($totalAuthorized - $totalUsed + $totalReturned);
    }

    /**
     * Increment escrow balance (atomic operation).
     */
    public function incrementBalance(Business $business, float $amount): void
    {
        DB::transaction(function () use ($business, $amount) {
            $business->lockForUpdate();
            $business->increment('escrow_balance', $amount);

            Log::debug('Escrow balance incremented', [
                'business_id' => $business->id,
                'amount' => $amount,
                'new_balance' => $business->fresh()->escrow_balance,
            ]);
        });
    }

    /**
     * Decrement escrow balance (atomic operation).
     */
    public function decrementBalance(Business $business, float $amount): void
    {
        DB::transaction(function () use ($business, $amount) {
            $business->lockForUpdate();
            $business->decrement('escrow_balance', $amount);

            Log::debug('Escrow balance decremented', [
                'business_id' => $business->id,
                'amount' => $amount,
                'new_balance' => $business->fresh()->escrow_balance,
            ]);
        });
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
     */
    public function reserveFunds(Business $business, float $amount, PaymentJob $paymentJob): bool
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
                Log::info('Payment job already has escrow deposit assigned', [
                    'payment_job_id' => $paymentJob->id,
                    'escrow_deposit_id' => $paymentJob->escrow_deposit_id,
                ]);

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

            Log::info('Funds reserved for payment', [
                'payment_job_id' => $paymentJob->id,
                'deposit_id' => $deposit->id,
                'amount' => $amount,
            ]);

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

            Log::info('Fund return manually recorded', [
                'payment_job_id' => $paymentJob->id,
                'deposit_id' => $paymentJob->escrowDeposit->id,
                'amount' => $paymentJob->amount,
                'recorded_by' => $recordedBy->id,
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
            $business = $payrollJob->payrollSchedule->business;
            $this->incrementBalance($business, $payrollJob->gross_salary);

            Log::info('Payroll fund return manually recorded', [
                'payroll_job_id' => $payrollJob->id,
                'deposit_id' => $payrollJob->escrowDeposit->id,
                'amount' => $payrollJob->gross_salary,
                'recorded_by' => $recordedBy->id,
            ]);
        });
    }
}
