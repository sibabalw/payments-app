<?php

namespace App\Services\PaymentGateway;

use Illuminate\Support\Facades\Config;

class PaymentGatewayFactory
{
    /**
     * Create a payment gateway instance based on configuration.
     */
    public static function make(?string $gateway = null): PaymentGatewayInterface
    {
        $gateway = $gateway ?? Config::get('payment.gateway', 'mock');

        return match ($gateway) {
            'mock' => new MockPaymentGateway(
                Config::get('payment.mock.success_rate', 0.95)
            ),
            default => throw new \InvalidArgumentException("Unsupported payment gateway: {$gateway}"),
        };
    }
}
