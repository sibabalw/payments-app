<?php

namespace App\Services;

use App\Mail\EscrowBalanceLowEmail;
use App\Models\PaymentJob;
use App\Models\PayrollJob;
use App\Services\PaymentGateway\PaymentGatewayFactory;
use App\Services\PaymentGateway\PaymentGatewayInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentService
{
    protected PaymentGatewayInterface $gateway;

    protected EscrowService $escrowService;

    public function __construct(?PaymentGatewayInterface $gateway = null, ?EscrowService $escrowService = null)
    {
        $this->gateway = $gateway ?? PaymentGatewayFactory::make();
        $this->escrowService = $escrowService ?? new EscrowService;
    }

    /**
     * Process a payment job (with recipients).
     * Uses idempotency, database locks, and transactions for safety.
     */
    public function processPaymentJob(PaymentJob $paymentJob): bool
    {
        // Generate idempotency key
        $idempotencyKey = 'payment_job_'.$paymentJob->id.'_'.($paymentJob->transaction_id ?? 'new');

        $idempotencyService = app(IdempotencyService::class);

        return $idempotencyService->execute($idempotencyKey, function () use ($paymentJob) {
            return DB::transaction(function () use ($paymentJob) {
                try {
                    $paymentJobId = $paymentJob->id;

                    // Lock the payment job row to prevent concurrent processing
                    $paymentJob = PaymentJob::where('id', $paymentJobId)
                        ->lockForUpdate()
                        ->first();

                    if (! $paymentJob) {
                        Log::error('Payment job not found', [
                            'payment_job_id' => $paymentJobId,
                        ]);

                        return false;
                    }

                    // Check if already processed
                    if (in_array($paymentJob->status, ['succeeded', 'processing'])) {
                        Log::info('Payment job already processed', [
                            'payment_job_id' => $paymentJob->id,
                            'status' => $paymentJob->status,
                        ]);

                        return $paymentJob->status === 'succeeded';
                    }

                    $business = $paymentJob->paymentSchedule->business;

                    // Check escrow balance before processing
                    $availableBalance = $this->escrowService->getAvailableBalance($business);
                    if ($availableBalance < $paymentJob->amount) {
                        $errorMessage = 'Insufficient escrow balance. Available: '.number_format($availableBalance, 2).', Required: '.number_format($paymentJob->amount, 2);

                        // Update status instead of moving/deleting
                        $paymentJob->update([
                            'status' => 'failed',
                            'processed_at' => now(),
                            'error_message' => $errorMessage,
                        ]);

                        Log::warning('Payment rejected due to insufficient escrow balance', [
                            'payment_job_id' => $paymentJob->id,
                            'available_balance' => $availableBalance,
                            'required_amount' => $paymentJob->amount,
                        ]);

                        // Send escrow balance low email
                        $user = $business->owner;
                        $emailService = app(EmailService::class);
                        $emailService->send(
                            $user,
                            new EscrowBalanceLowEmail($user, $business, $availableBalance, $paymentJob->amount),
                            'escrow_balance_low'
                        );

                        return false;
                    }

                    // Reserve funds from escrow (already has transaction and locks)
                    $fundsReserved = $this->escrowService->reserveFunds($business, $paymentJob->amount, $paymentJob);
                    if (! $fundsReserved) {
                        $errorMessage = 'Failed to reserve funds from escrow account';

                        // Update status instead of moving/deleting
                        $paymentJob->update([
                            'status' => 'failed',
                            'processed_at' => now(),
                            'error_message' => $errorMessage,
                        ]);

                        Log::error('Failed to reserve escrow funds', [
                            'payment_job_id' => $paymentJob->id,
                        ]);

                        return false;
                    }

                    // Skip real payment processing - just update status
                    $paymentJob->update([
                        'status' => 'succeeded',
                        'processed_at' => now(),
                        'transaction_id' => 'SKIPPED-'.now()->format('YmdHis').'-'.$paymentJob->id,
                    ]);

                    // Decrement escrow balance
                    $this->escrowService->decrementBalance($business, $paymentJob->amount);

                    Log::info('Payment processed successfully', [
                        'payment_job_id' => $paymentJob->id,
                        'amount' => $paymentJob->amount,
                        'currency' => $paymentJob->currency,
                        'recipient_id' => $paymentJob->recipient_id,
                    ]);

                    return true;
                } catch (\Exception $e) {
                    // Update status instead of moving/deleting
                    if ($paymentJob) {
                        $paymentJob->update([
                            'status' => 'failed',
                            'processed_at' => now(),
                            'error_message' => $e->getMessage(),
                        ]);
                    }

                    Log::error('Payment processing exception', [
                        'payment_job_id' => $paymentJob?->id,
                        'exception' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);

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
        // Generate idempotency key
        $idempotencyKey = 'payroll_job_'.$payrollJob->id.'_'.($payrollJob->transaction_id ?? 'new');

        $idempotencyService = app(IdempotencyService::class);

        return $idempotencyService->execute($idempotencyKey, function () use ($payrollJob) {
            return DB::transaction(function () use ($payrollJob) {
                try {
                    $payrollJobId = $payrollJob->id;

                    // Lock the payroll job row to prevent concurrent processing
                    $payrollJob = PayrollJob::where('id', $payrollJobId)
                        ->lockForUpdate()
                        ->first();

                    if (! $payrollJob) {
                        Log::error('Payroll job not found', [
                            'payroll_job_id' => $payrollJobId,
                        ]);

                        return false;
                    }

                    // Check if already processed
                    if (in_array($payrollJob->status, ['succeeded', 'processing'])) {
                        Log::info('Payroll job already processed', [
                            'payroll_job_id' => $payrollJob->id,
                            'status' => $payrollJob->status,
                        ]);

                        return $payrollJob->status === 'succeeded';
                    }

                    $business = $payrollJob->payrollSchedule->business;

                    // Use net salary as payment amount (taxes and deductions already calculated)
                    $paymentAmount = $payrollJob->net_salary;

                    // Check escrow balance before processing
                    $availableBalance = $this->escrowService->getAvailableBalance($business);
                    if ($availableBalance < $paymentAmount) {
                        $errorMessage = 'Insufficient escrow balance. Available: '.number_format($availableBalance, 2).', Required: '.number_format($paymentAmount, 2);

                        // Update status instead of moving/deleting
                        $payrollJob->update([
                            'status' => 'failed',
                            'processed_at' => now(),
                            'error_message' => $errorMessage,
                        ]);

                        Log::warning('Payroll payment rejected due to insufficient escrow balance', [
                            'payroll_job_id' => $payrollJob->id,
                            'available_balance' => $availableBalance,
                            'required_amount' => $paymentAmount,
                        ]);

                        // Send escrow balance low email
                        $user = $business->owner;
                        $emailService = app(EmailService::class);
                        $emailService->send(
                            $user,
                            new EscrowBalanceLowEmail($user, $business, $availableBalance, $paymentAmount),
                            'escrow_balance_low'
                        );

                        return false;
                    }

                    // Reserve funds from escrow (already has transaction and locks)
                    $fundsReserved = $this->escrowService->reserveFundsForPayroll($business, $paymentAmount, $payrollJob);
                    if (! $fundsReserved) {
                        $errorMessage = 'Failed to reserve funds from escrow account';

                        // Update status instead of moving/deleting
                        $payrollJob->update([
                            'status' => 'failed',
                            'processed_at' => now(),
                            'error_message' => $errorMessage,
                        ]);

                        Log::error('Failed to reserve escrow funds for payroll', [
                            'payroll_job_id' => $payrollJob->id,
                        ]);

                        return false;
                    }

                    // Skip real payment processing - just update status
                    $payrollJob->update([
                        'status' => 'succeeded',
                        'processed_at' => now(),
                        'transaction_id' => 'SKIPPED-'.now()->format('YmdHis').'-'.$payrollJob->id,
                    ]);

                    // Decrement escrow balance
                    $this->escrowService->decrementBalance($business, $paymentAmount);

                    // Ensure custom_deductions is an array for counting
                    $customDeductions = $payrollJob->custom_deductions;
                    if (is_string($customDeductions)) {
                        $customDeductions = json_decode($customDeductions, true) ?? [];
                    }
                    if (! is_array($customDeductions)) {
                        $customDeductions = [];
                    }

                    Log::info('Payroll payment processed successfully', [
                        'payroll_job_id' => $payrollJob->id,
                        'gross_salary' => $payrollJob->gross_salary,
                        'net_salary' => $payrollJob->net_salary,
                        'paye' => $payrollJob->paye_amount,
                        'uif' => $payrollJob->uif_amount,
                        'sdl' => $payrollJob->sdl_amount,
                        'custom_deductions' => $customDeductions,
                        'custom_deductions_count' => count($customDeductions),
                        'currency' => $payrollJob->currency,
                        'employee_id' => $payrollJob->employee_id,
                    ]);

                    return true;
                } catch (\Exception $e) {
                    // Update status instead of moving/deleting
                    if ($payrollJob) {
                        $payrollJob->update([
                            'status' => 'failed',
                            'processed_at' => now(),
                            'error_message' => $e->getMessage(),
                        ]);
                    }

                    Log::error('Payroll processing exception', [
                        'payroll_job_id' => $payrollJob?->id,
                        'exception' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);

                    return false;
                }
            });
        });
    }
}
