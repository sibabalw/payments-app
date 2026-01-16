<?php

namespace App\Services;

use App\Mail\EscrowBalanceLowEmail;
use App\Models\PaymentJob;
use App\Models\Receiver;
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
     * Process a payment job.
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
                        $paymentJob->update([
                            'status' => 'failed',
                            'error_message' => 'Insufficient escrow balance. Available: ' . number_format($availableBalance, 2) . ', Required: ' . number_format($paymentJob->amount, 2),
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
                    if (!$fundsReserved) {
                        $paymentJob->update([
                            'status' => 'failed',
                            'error_message' => 'Failed to reserve funds from escrow account',
                        ]);

                        Log::error('Failed to reserve escrow funds', [
                            'payment_job_id' => $paymentJob->id,
                        ]);

                        return false;
                    }

                    $paymentJob->update(['status' => 'processing']);

                    // Skip real payment processing - just create database record
                    // Mark as succeeded immediately without calling payment gateway
                    $paymentJob->update([
                        'status' => 'succeeded',
                        'processed_at' => now(),
                        'error_message' => null,
                        'transaction_id' => 'SKIPPED-' . now()->format('YmdHis') . '-' . $paymentJob->id,
                    ]);

                    Log::info('Payment record created (real payment skipped)', [
                        'payment_job_id' => $paymentJob->id,
                        'amount' => $paymentJob->amount,
                        'currency' => $paymentJob->currency,
                        'receiver_id' => $paymentJob->receiver_id,
                    ]);

                    return true;
                } catch (\Exception $e) {
                    // Bank will handle fund returns - admin will record manually

                    $paymentJob->update([
                        'status' => 'failed',
                        'error_message' => $e->getMessage(),
                    ]);

                    Log::error('Payment processing exception', [
                        'payment_job_id' => $paymentJob->id,
                        'exception' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);

                    return false;
                }
            });
        });
    }
}
