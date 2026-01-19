<?php

namespace App\Services\BillingGateway;

use App\Models\Business;

interface BillingGatewayInterface
{
    /**
     * Charge a subscription fee to a business's bank account.
     */
    public function chargeSubscription(float $amount, string $currency, Business $business, array $metadata = []): BillingResult;
}
