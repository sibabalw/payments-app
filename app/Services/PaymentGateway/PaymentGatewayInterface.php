<?php

namespace App\Services\PaymentGateway;

use App\Models\Receiver;

interface PaymentGatewayInterface
{
    /**
     * Process a payment to a receiver.
     *
     * @param  float  $amount
     * @param  string  $currency
     * @param  Receiver  $receiver
     * @param  array  $metadata
     * @return PaymentResult
     */
    public function processPayment(float $amount, string $currency, Receiver $receiver, array $metadata = []): PaymentResult;
}
