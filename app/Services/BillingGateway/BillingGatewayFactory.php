<?php

namespace App\Services\BillingGateway;

use Illuminate\Support\Facades\Config;

class BillingGatewayFactory
{
    /**
     * Create a billing gateway instance based on configuration.
     */
    public static function make(?string $gateway = null): BillingGatewayInterface
    {
        $gateway = $gateway ?? Config::get('billing.gateway', 'mock');

        return match ($gateway) {
            'mock' => new MockBillingGateway(
                Config::get('billing.mock.success_rate', 0.95)
            ),
            default => throw new \InvalidArgumentException("Unsupported billing gateway: {$gateway}"),
        };
    }
}
