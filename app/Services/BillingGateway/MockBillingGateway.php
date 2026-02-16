<?php

namespace App\Services\BillingGateway;

use App\Models\Business;

class MockBillingGateway implements BillingGatewayInterface
{
    /**
     * Success rate for mock billing charges (0.0 to 1.0)
     */
    protected float $successRate;

    public function __construct(float $successRate = 0.95)
    {
        $this->successRate = $successRate;
    }

    public function chargeSubscription(float $amount, string $currency, Business $business, array $metadata = []): BillingResult
    {
        // Simulate processing delay
        usleep(rand(100000, 500000)); // 0.1 to 0.5 seconds

        // Simulate success/failure based on success rate
        $success = (mt_rand() / mt_getrandmax()) <= $this->successRate;

        if ($success) {
            $transactionId = 'billing_mock_'.uniqid().'_'.time();

            return BillingResult::success($transactionId, [
                'gateway' => 'mock',
                'processed_at' => now()->toIso8601String(),
                'business_id' => $business->id,
                'amount' => $amount,
                'currency' => $currency,
            ]);
        }

        $errorMessages = [
            'Insufficient funds in business account',
            'Invalid bank account details',
            'Bank account not found',
            'Network timeout',
            'Billing gateway temporarily unavailable',
            'Account closed or frozen',
        ];

        return BillingResult::failure(
            $errorMessages[array_rand($errorMessages)],
            [
                'gateway' => 'mock',
                'attempted_at' => now()->toIso8601String(),
                'business_id' => $business->id,
                'amount' => $amount,
                'currency' => $currency,
            ]
        );
    }
}
