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
    public function recordManualDeposit(Business $business, float $amount, string $currency = 'ZAR', ?string $bankReference = null, User $enteredBy): EscrowDeposit
    {
        $feeAmount = $this->calculateFee($amount);
        $authorizedAmount = $this->getAuthorizedAmount($amount);

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

        Log::info('Escrow deposit manually recorded', [
            'deposit_id' => $deposit->id,
            'business_id' => $business->id,
            'amount' => $amount,
            'authorized_amount' => $authorizedAmount,
            'entry_method' => 'manual',
            'entered_by' => $enteredBy->id,
        ]);

        return $deposit;
    }

    /**
     * Confirm a pending deposit (admin action after bank processes it).
     */
    public function confirmDeposit(EscrowDeposit $deposit, ?string $bankReference = null, User $confirmedBy): void
    {
        $deposit->update([
            'status' => 'confirmed',
            'bank_reference' => $bankReference ?? $deposit->bank_reference,
            'completed_at' => now(),
        ]);

        Log::info('Escrow deposit confirmed', [
            'deposit_id' => $deposit->id,
            'confirmed_by' => $confirmedBy->id,
        ]);
    }

    /**
     * Get available balance for a business.
     * Calculated from confirmed deposits minus used amounts.
     * Uses shared lock for consistent reads within transaction.
     */
    public function getAvailableBalance(Business $business): float
    {
        return DB::transaction(function () use ($business) {
            // Use shared lock for consistent reads
            // Sum of authorized amounts from confirmed deposits
            $totalAuthorized = EscrowDeposit::where('business_id', $business->id)
                ->where('status', 'confirmed')
                ->sharedLock()
                ->sum('authorized_amount');

            // Sum of amounts from payment jobs that used escrow funds
            $totalUsed = PaymentJob::whereHas('paymentSchedule', function ($query) use ($business) {
                $query->where('business_id', $business->id);
            })
            ->whereNotNull('escrow_deposit_id')
            ->whereIn('status', ['succeeded', 'processing'])
            ->sharedLock()
            ->sum('amount');

            // Funds returned manually
            $totalReturned = PaymentJob::whereHas('paymentSchedule', function ($query) use ($business) {
                $query->where('business_id', $business->id);
            })
            ->whereNotNull('funds_returned_manually_at')
            ->sharedLock()
            ->sum('amount');

            return (float) ($totalAuthorized - $totalUsed + $totalReturned);
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

            if (!$paymentJob) {
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

            if (!$deposit) {
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

            if (!$payrollJob) {
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

            if (!$deposit) {
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
        if (!$paymentJob->escrowDeposit) {
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
        if (!$paymentJob->escrowDeposit) {
            throw new \Exception('Payment job does not have an associated escrow deposit');
        }

        $paymentJob->update([
            'funds_returned_manually_at' => now(),
            'released_by' => $recordedBy->id,
        ]);

        Log::info('Fund return manually recorded', [
            'payment_job_id' => $paymentJob->id,
            'deposit_id' => $paymentJob->escrowDeposit->id,
            'amount' => $paymentJob->amount,
            'recorded_by' => $recordedBy->id,
        ]);
    }
}
