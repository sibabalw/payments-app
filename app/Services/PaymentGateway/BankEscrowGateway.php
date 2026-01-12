<?php

namespace App\Services\PaymentGateway;

use App\Models\Business;
use Illuminate\Support\Facades\Log;

class BankEscrowGateway implements EscrowGatewayInterface
{
    /**
     * Create an escrow account for a business.
     * No-op: Platform uses a single escrow account, not created per business.
     */
    public function createEscrowAccount(Business $business): EscrowAccountResult
    {
        Log::warning('createEscrowAccount called but platform uses single account', [
            'business_id' => $business->id,
        ]);

        return EscrowAccountResult::failure('Platform uses a single escrow account, not per-business accounts');
    }

    /**
     * Process a deposit into the escrow account.
     * No-op: Bank handles deposits, we just record them manually.
     */
    public function processDeposit(float $amount, string $currency, array $metadata = []): EscrowDepositResult
    {
        Log::warning('processDeposit called but bank handles deposits', [
            'amount' => $amount,
            'currency' => $currency,
        ]);

        return EscrowDepositResult::failure('Bank handles deposits - record manually via admin interface');
    }

    /**
     * Release fee to platform on successful payment execution.
     * No-op: Bank handles fee releases, we record them manually.
     */
    public function releaseFee(string $escrowReference, float $feeAmount, array $metadata = []): EscrowOperationResult
    {
        Log::warning('releaseFee called but bank handles fee releases', [
            'escrow_reference' => $escrowReference,
            'fee_amount' => $feeAmount,
        ]);

        return EscrowOperationResult::failure('Bank handles fee releases - record manually via admin interface');
    }

    /**
     * Return funds to business on failed payment execution.
     * No-op: Bank handles fund returns, we record them manually.
     */
    public function returnFunds(string $escrowReference, float $amount, array $metadata = []): EscrowOperationResult
    {
        Log::warning('returnFunds called but bank handles fund returns', [
            'escrow_reference' => $escrowReference,
            'amount' => $amount,
        ]);

        return EscrowOperationResult::failure('Bank handles fund returns - record manually via admin interface');
    }
}
