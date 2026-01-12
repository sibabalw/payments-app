<?php

namespace App\Services\PaymentGateway;

use App\Models\Business;

interface EscrowGatewayInterface
{
    /**
     * Create an escrow account for a business.
     */
    public function createEscrowAccount(Business $business): EscrowAccountResult;

    /**
     * Process a deposit into the escrow account.
     */
    public function processDeposit(float $amount, string $currency, array $metadata = []): EscrowDepositResult;

    /**
     * Release fee to platform on successful payment execution.
     */
    public function releaseFee(string $escrowReference, float $feeAmount, array $metadata = []): EscrowOperationResult;

    /**
     * Return funds to business on failed payment execution.
     */
    public function returnFunds(string $escrowReference, float $amount, array $metadata = []): EscrowOperationResult;
}
