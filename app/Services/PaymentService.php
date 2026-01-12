<?php

namespace App\Services;

use App\Models\PaymentJob;
use App\Models\Receiver;
use App\Services\PaymentGateway\PaymentGatewayFactory;
use App\Services\PaymentGateway\PaymentGatewayInterface;
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
     */
    public function processPaymentJob(PaymentJob $paymentJob): bool
    {
        try {
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

                return false;
            }

            // Reserve funds from escrow
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
    }
}
