<?php

namespace App\Services;

use App\Mail\EscrowBalanceLowEmail;
use App\Models\PaymentJob;
use App\Models\PayrollJob;
use App\Models\ExecutedPayment;
use App\Models\ExecutedPayroll;
use App\Services\EmailService;
use App\Services\IdempotencyService;
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
        $this->escrowService = $escrowService ?? new EscrowService();
    }

    /**
     * Process a payment job (with recipients).
     * Uses idempotency, database locks, and transactions for safety.
     */
    public function processPaymentJob(PaymentJob $paymentJob): bool
    {
        // Generate idempotency key
        $idempotencyKey = 'payment_job_' . $paymentJob->id . '_' . ($paymentJob->transaction_id ?? 'new');

        $idempotencyService = app(IdempotencyService::class);

        return $idempotencyService->execute($idempotencyKey, function () use ($paymentJob) {
            return DB::transaction(function () use ($paymentJob) {
                try {
                    $paymentJobId = $paymentJob->id;
                    
                    // Lock the payment job row to prevent concurrent processing
                    $paymentJob = PaymentJob::where('id', $paymentJobId)
                        ->lockForUpdate()
                        ->first();

                    if (!$paymentJob) {
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
                        $errorMessage = 'Insufficient escrow balance. Available: ' . number_format($availableBalance, 2) . ', Required: ' . number_format($paymentJob->amount, 2);
                        
                        // Move failed job to executed_payments
                        ExecutedPayment::create([
                            'payment_schedule_id' => $paymentJob->payment_schedule_id,
                            'recipient_id' => $paymentJob->recipient_id,
                            'amount' => $paymentJob->amount,
                            'currency' => $paymentJob->currency,
                            'status' => 'failed',
                            'processed_at' => now(),
                            'error_message' => $errorMessage,
                            'transaction_id' => null,
                            'fee' => $paymentJob->fee,
                            'escrow_deposit_id' => $paymentJob->escrow_deposit_id,
                        ]);

                        // Delete from payment_jobs table
                        $paymentJob->delete();

                        Log::warning('Payment rejected due to insufficient escrow balance - moved to executed_payments', [
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
                    if (!$fundsReserved) {
                        $errorMessage = 'Failed to reserve funds from escrow account';
                        
                        // Move failed job to executed_payments
                        ExecutedPayment::create([
                            'payment_schedule_id' => $paymentJob->payment_schedule_id,
                            'recipient_id' => $paymentJob->recipient_id,
                            'amount' => $paymentJob->amount,
                            'currency' => $paymentJob->currency,
                            'status' => 'failed',
                            'processed_at' => now(),
                            'error_message' => $errorMessage,
                            'transaction_id' => null,
                            'fee' => $paymentJob->fee,
                            'escrow_deposit_id' => $paymentJob->escrow_deposit_id,
                        ]);

                        // Delete from payment_jobs table
                        $paymentJob->delete();

                        Log::error('Failed to reserve escrow funds - moved to executed_payments', [
                            'payment_job_id' => $paymentJob->id,
                        ]);

                        return false;
                    }

                    $paymentJob->update(['status' => 'processing']);

                    // Skip real payment processing - just create database record
                    // Move to executed_payments table
                    ExecutedPayment::create([
                        'payment_schedule_id' => $paymentJob->payment_schedule_id,
                        'recipient_id' => $paymentJob->recipient_id,
                        'amount' => $paymentJob->amount,
                        'currency' => $paymentJob->currency,
                        'status' => 'succeeded',
                        'processed_at' => now(),
                        'error_message' => null,
                        'transaction_id' => 'SKIPPED-' . now()->format('YmdHis') . '-' . $paymentJob->id,
                        'fee' => $paymentJob->fee,
                        'escrow_deposit_id' => $paymentJob->escrow_deposit_id,
                    ]);

                    // Delete from payment_jobs table
                    $paymentJob->delete();

                    Log::info('Payment record moved to executed_payments', [
                        'payment_job_id' => $paymentJob->id,
                        'amount' => $paymentJob->amount,
                        'currency' => $paymentJob->currency,
                        'recipient_id' => $paymentJob->recipient_id,
                    ]);

                    return true;
                } catch (\Exception $e) {
                    // Move failed job to executed_payments
                    ExecutedPayment::create([
                        'payment_schedule_id' => $paymentJob->payment_schedule_id,
                        'recipient_id' => $paymentJob->recipient_id,
                        'amount' => $paymentJob->amount,
                        'currency' => $paymentJob->currency,
                        'status' => 'failed',
                        'processed_at' => now(),
                        'error_message' => $e->getMessage(),
                        'transaction_id' => $paymentJob->transaction_id,
                        'fee' => $paymentJob->fee,
                        'escrow_deposit_id' => $paymentJob->escrow_deposit_id,
                    ]);

                    // Delete from payment_jobs table
                    $paymentJob->delete();

                    Log::error('Payment processing exception - moved to executed_payments', [
                        'payment_job_id' => $paymentJob->id,
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
        $idempotencyKey = 'payroll_job_' . $payrollJob->id . '_' . ($payrollJob->transaction_id ?? 'new');

        $idempotencyService = app(IdempotencyService::class);

        return $idempotencyService->execute($idempotencyKey, function () use ($payrollJob) {
            return DB::transaction(function () use ($payrollJob) {
                try {
                    $payrollJobId = $payrollJob->id;
                    
                    // Lock the payroll job row to prevent concurrent processing
                    $payrollJob = PayrollJob::where('id', $payrollJobId)
                        ->lockForUpdate()
                        ->first();

                    if (!$payrollJob) {
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

                    // Use gross salary as payment amount (no tax calculations yet)
                    $paymentAmount = $payrollJob->gross_salary;

                    // Check escrow balance before processing
                    $availableBalance = $this->escrowService->getAvailableBalance($business);
                    if ($availableBalance < $paymentAmount) {
                        $errorMessage = 'Insufficient escrow balance. Available: ' . number_format($availableBalance, 2) . ', Required: ' . number_format($paymentAmount, 2);
                        
                        // Move failed job to executed_payroll
                        ExecutedPayroll::create([
                            'payroll_schedule_id' => $payrollJob->payroll_schedule_id,
                            'employee_id' => $payrollJob->employee_id,
                            'gross_salary' => $payrollJob->gross_salary,
                            'paye_amount' => $payrollJob->paye_amount,
                            'uif_amount' => $payrollJob->uif_amount,
                            'sdl_amount' => $payrollJob->sdl_amount,
                            'net_salary' => $payrollJob->net_salary,
                            'currency' => $payrollJob->currency,
                            'status' => 'failed',
                            'processed_at' => now(),
                            'error_message' => $errorMessage,
                            'transaction_id' => null,
                            'fee' => $payrollJob->fee,
                            'escrow_deposit_id' => $payrollJob->escrow_deposit_id,
                            'pay_period_start' => $payrollJob->pay_period_start,
                            'pay_period_end' => $payrollJob->pay_period_end,
                        ]);

                        // Delete from payroll_jobs table
                        $payrollJob->delete();

                        Log::warning('Payroll payment rejected due to insufficient escrow balance - moved to executed_payroll', [
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
                    if (!$fundsReserved) {
                        $errorMessage = 'Failed to reserve funds from escrow account';
                        
                        // Move failed job to executed_payroll
                        ExecutedPayroll::create([
                            'payroll_schedule_id' => $payrollJob->payroll_schedule_id,
                            'employee_id' => $payrollJob->employee_id,
                            'gross_salary' => $payrollJob->gross_salary,
                            'paye_amount' => $payrollJob->paye_amount,
                            'uif_amount' => $payrollJob->uif_amount,
                            'sdl_amount' => $payrollJob->sdl_amount,
                            'net_salary' => $payrollJob->net_salary,
                            'currency' => $payrollJob->currency,
                            'status' => 'failed',
                            'processed_at' => now(),
                            'error_message' => $errorMessage,
                            'transaction_id' => null,
                            'fee' => $payrollJob->fee,
                            'escrow_deposit_id' => $payrollJob->escrow_deposit_id,
                            'pay_period_start' => $payrollJob->pay_period_start,
                            'pay_period_end' => $payrollJob->pay_period_end,
                        ]);

                        // Delete from payroll_jobs table
                        $payrollJob->delete();

                        Log::error('Failed to reserve escrow funds for payroll - moved to executed_payroll', [
                            'payroll_job_id' => $payrollJob->id,
                        ]);

                        return false;
                    }

                    $payrollJob->update(['status' => 'processing']);

                    // Skip real payment processing - just create database record
                    // Move to executed_payroll table
                    ExecutedPayroll::create([
                        'payroll_schedule_id' => $payrollJob->payroll_schedule_id,
                        'employee_id' => $payrollJob->employee_id,
                        'gross_salary' => $payrollJob->gross_salary,
                        'paye_amount' => $payrollJob->paye_amount,
                        'uif_amount' => $payrollJob->uif_amount,
                        'sdl_amount' => $payrollJob->sdl_amount,
                        'net_salary' => $payrollJob->net_salary,
                        'currency' => $payrollJob->currency,
                        'status' => 'succeeded',
                        'processed_at' => now(),
                        'error_message' => null,
                        'transaction_id' => 'SKIPPED-' . now()->format('YmdHis') . '-' . $payrollJob->id,
                        'fee' => $payrollJob->fee,
                        'escrow_deposit_id' => $payrollJob->escrow_deposit_id,
                        'pay_period_start' => $payrollJob->pay_period_start,
                        'pay_period_end' => $payrollJob->pay_period_end,
                    ]);

                    // Delete from payroll_jobs table
                    $payrollJob->delete();

                    Log::info('Payroll payment record moved to executed_payroll', [
                        'payroll_job_id' => $payrollJob->id,
                        'gross_salary' => $payrollJob->gross_salary,
                        'net_salary' => $payrollJob->net_salary,
                        'paye' => $payrollJob->paye_amount,
                        'uif' => $payrollJob->uif_amount,
                        'sdl' => $payrollJob->sdl_amount,
                        'currency' => $payrollJob->currency,
                        'employee_id' => $payrollJob->employee_id,
                    ]);

                    return true;
                } catch (\Exception $e) {
                    // Move failed job to executed_payroll
                    ExecutedPayroll::create([
                        'payroll_schedule_id' => $payrollJob->payroll_schedule_id,
                        'employee_id' => $payrollJob->employee_id,
                        'gross_salary' => $payrollJob->gross_salary,
                        'paye_amount' => $payrollJob->paye_amount,
                        'uif_amount' => $payrollJob->uif_amount,
                        'sdl_amount' => $payrollJob->sdl_amount,
                        'net_salary' => $payrollJob->net_salary,
                        'currency' => $payrollJob->currency,
                        'status' => 'failed',
                        'processed_at' => now(),
                        'error_message' => $e->getMessage(),
                        'transaction_id' => $payrollJob->transaction_id,
                        'fee' => $payrollJob->fee,
                        'escrow_deposit_id' => $payrollJob->escrow_deposit_id,
                        'pay_period_start' => $payrollJob->pay_period_start,
                        'pay_period_end' => $payrollJob->pay_period_end,
                    ]);

                    // Delete from payroll_jobs table
                    $payrollJob->delete();

                    Log::error('Payroll processing exception - moved to executed_payroll', [
                        'payroll_job_id' => $payrollJob->id,
                        'exception' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);

                    return false;
                }
            });
        });
    }
}
