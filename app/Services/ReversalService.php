<?php

namespace App\Services;

use App\Models\FinancialLedger;
use App\Models\PaymentJob;
use App\Models\PayrollJob;
use App\Models\TransactionReversal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReversalService
{
    protected FinancialLedgerService $ledgerService;

    protected EscrowService $escrowService;

    public function __construct(
        ?FinancialLedgerService $ledgerService = null,
        ?EscrowService $escrowService = null
    ) {
        $this->ledgerService = $ledgerService ?? app(FinancialLedgerService::class);
        $this->escrowService = $escrowService ?? app(EscrowService::class);
    }

    /**
     * Reverse a payment job
     */
    public function reversePaymentJob(PaymentJob $paymentJob, string $reason = 'automatic', ?\App\Models\User $user = null): bool
    {
        return DB::transaction(function () use ($paymentJob, $reason, $user) {
            // Check if already reversed
            $existing = TransactionReversal::where('reversible_type', PaymentJob::class)
                ->where('reversible_id', $paymentJob->id)
                ->where('status', 'completed')
                ->first();

            if ($existing) {
                return true; // Already reversed
            }

            // Create reversal record
            $reversal = TransactionReversal::create([
                'reversible_type' => PaymentJob::class,
                'reversible_id' => $paymentJob->id,
                'reversal_type' => $user ? 'manual' : 'automatic',
                'reason' => $reason,
                'status' => 'pending',
                'reversed_by' => $user?->id,
            ]);

            try {
                // Find ledger entries for this payment
                $ledgerEntries = FinancialLedger::where('reference_type', PaymentJob::class)
                    ->where('reference_id', $paymentJob->id)
                    ->whereNull('reversal_of_id')
                    ->get();

                // Reverse ledger entries
                foreach ($ledgerEntries as $entry) {
                    $this->ledgerService->reverseTransaction($entry, $reason, $user);
                }

                // Return funds to escrow
                if ($paymentJob->status === 'succeeded') {
                    $business = $paymentJob->paymentSchedule->business;
                    $this->escrowService->incrementBalance($business, $paymentJob->amount);
                }

                // Update reversal status
                $reversal->update([
                    'status' => 'completed',
                    'reversed_at' => now(),
                ]);

                Log::info('Payment job reversed', [
                    'payment_job_id' => $paymentJob->id,
                    'reversal_id' => $reversal->id,
                    'reason' => $reason,
                ]);

                return true;
            } catch (\Exception $e) {
                $reversal->update(['status' => 'failed']);
                Log::error('Payment job reversal failed', [
                    'payment_job_id' => $paymentJob->id,
                    'error' => $e->getMessage(),
                ]);

                throw $e;
            }
        });
    }

    /**
     * Reverse a payroll job
     */
    public function reversePayrollJob(PayrollJob $payrollJob, string $reason = 'automatic', ?\App\Models\User $user = null): bool
    {
        return DB::transaction(function () use ($payrollJob, $reason, $user) {
            // Check if already reversed
            $existing = TransactionReversal::where('reversible_type', PayrollJob::class)
                ->where('reversible_id', $payrollJob->id)
                ->where('status', 'completed')
                ->first();

            if ($existing) {
                return true; // Already reversed
            }

            // Create reversal record
            $reversal = TransactionReversal::create([
                'reversible_type' => PayrollJob::class,
                'reversible_id' => $payrollJob->id,
                'reversal_type' => $user ? 'manual' : 'automatic',
                'reason' => $reason,
                'status' => 'pending',
                'reversed_by' => $user?->id,
            ]);

            try {
                // Find ledger entries for this payroll
                $ledgerEntries = FinancialLedger::where('reference_type', PayrollJob::class)
                    ->where('reference_id', $payrollJob->id)
                    ->whereNull('reversal_of_id')
                    ->get();

                // Reverse ledger entries
                foreach ($ledgerEntries as $entry) {
                    $this->ledgerService->reverseTransaction($entry, $reason, $user);
                }

                // Return funds to escrow
                if ($payrollJob->status === 'succeeded') {
                    $business = $payrollJob->payrollSchedule->business;
                    $this->escrowService->incrementBalance($business, $payrollJob->net_salary);
                }

                // Update reversal status
                $reversal->update([
                    'status' => 'completed',
                    'reversed_at' => now(),
                ]);

                Log::info('Payroll job reversed', [
                    'payroll_job_id' => $payrollJob->id,
                    'reversal_id' => $reversal->id,
                    'reason' => $reason,
                ]);

                return true;
            } catch (\Exception $e) {
                $reversal->update(['status' => 'failed']);
                Log::error('Payroll job reversal failed', [
                    'payroll_job_id' => $payrollJob->id,
                    'error' => $e->getMessage(),
                ]);

                throw $e;
            }
        });
    }
}
