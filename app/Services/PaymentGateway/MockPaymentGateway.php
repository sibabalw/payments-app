<?php

namespace App\Services\PaymentGateway;

use App\Models\Receiver;

class MockPaymentGateway implements PaymentGatewayInterface
{
    /**
     * Success rate for mock payments (0.0 to 1.0)
     */
    protected float $successRate;

    public function __construct(float $successRate = 0.95)
    {
        $this->successRate = $successRate;
    }

    public function processPayment(float $amount, string $currency, Receiver $receiver, array $metadata = []): PaymentResult
    {
        // Simulate processing delay
        usleep(rand(100000, 500000)); // 0.1 to 0.5 seconds

        // Simulate success/failure based on success rate
        $success = (mt_rand() / mt_getrandmax()) <= $this->successRate;

        if ($success) {
            $transactionId = 'mock_txn_'.uniqid().'_'.time();

            return PaymentResult::success($transactionId, [
                'gateway' => 'mock',
                'processed_at' => now()->toIso8601String(),
            ]);
        }

        $errorMessages = [
            'Insufficient funds',
            'Invalid bank account details',
            'Network timeout',
            'Receiver account not found',
            'Payment gateway temporarily unavailable',
        ];

        return PaymentResult::failure(
            $errorMessages[array_rand($errorMessages)],
            [
                'gateway' => 'mock',
                'attempted_at' => now()->toIso8601String(),
            ]
        );
    }
}
