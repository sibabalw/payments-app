<?php

namespace App\Services;

use App\Models\BillingTransaction;
use App\Models\Business;
use App\Models\EscrowDeposit;
use App\Models\MonthlyBilling;
use App\Services\BillingGateway\BillingGatewayFactory;
use App\Services\BillingGateway\BillingGatewayInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BillingService
{
    protected BillingGatewayInterface $billingGateway;

    public function __construct(?BillingGatewayInterface $billingGateway = null)
    {
        $this->billingGateway = $billingGateway ?? BillingGatewayFactory::make();
    }

    /**
     * Get fixed subscription fee based on business type.
     */
    public function getSubscriptionFee(Business $business): float
    {
        $businessType = $this->getBusinessType($business);

        return match ($businessType) {
            'small_business' => 1000.00,
            'other' => 2500.00,
            default => 1000.00,
        };
    }

    /**
     * Get business type from business model.
     */
    public function getBusinessType(Business $business): string
    {
        return $business->business_type ?? 'small_business';
    }

    /**
     * Generate monthly billing record for a business.
     */
    public function generateMonthlyBilling(Business $business, string $month): MonthlyBilling
    {
        return DB::transaction(function () use ($business, $month) {
            // Check if billing already exists for this month
            $existingBilling = MonthlyBilling::where('business_id', $business->id)
                ->where('billing_month', $month)
                ->first();

            if ($existingBilling) {
                return $existingBilling;
            }

            $businessType = $this->getBusinessType($business);
            $subscriptionFee = $this->getSubscriptionFee($business);

            // Calculate total deposit fees for the month
            $startDate = $month.'-01';
            $endDate = date('Y-m-t', strtotime($startDate));

            $totalDepositFees = EscrowDeposit::where('business_id', $business->id)
                ->where('status', 'completed')
                ->whereBetween('completed_at', [$startDate.' 00:00:00', $endDate.' 23:59:59'])
                ->sum('fee_amount');

            $billing = MonthlyBilling::create([
                'business_id' => $business->id,
                'billing_month' => $month,
                'business_type' => $businessType,
                'subscription_fee' => $subscriptionFee,
                'total_deposit_fees' => (float) $totalDepositFees,
                'status' => 'pending',
                'billed_at' => now(),
            ]);

            Log::info('Monthly billing generated', [
                'billing_id' => $billing->id,
                'business_id' => $business->id,
                'month' => $month,
                'subscription_fee' => $subscriptionFee,
                'total_deposit_fees' => $totalDepositFees,
            ]);

            return $billing;
        });
    }

    /**
     * Process subscription fee charge.
     */
    public function processSubscriptionFee(Business $business, MonthlyBilling $billing): bool
    {
        return DB::transaction(function () use ($business, $billing) {
            // Check if business has bank account details
            if (! $business->hasBankAccountDetails()) {
                Log::warning('Cannot process subscription fee: business has no bank account details', [
                    'billing_id' => $billing->id,
                    'business_id' => $business->id,
                ]);

                return false;
            }

            // Create billing transaction record
            $transaction = BillingTransaction::create([
                'business_id' => $business->id,
                'monthly_billing_id' => $billing->id,
                'type' => 'subscription_fee',
                'amount' => $billing->subscription_fee,
                'currency' => 'ZAR',
                'description' => "Monthly subscription fee for {$billing->billing_month}",
                'status' => 'pending',
            ]);

            // Charge via billing gateway
            $result = $this->billingGateway->chargeSubscription(
                (float) $billing->subscription_fee,
                'ZAR',
                $business,
                [
                    'billing_id' => $billing->id,
                    'billing_month' => $billing->billing_month,
                    'transaction_id' => $transaction->id,
                ]
            );

            // Update transaction record
            $transaction->update([
                'status' => $result->success ? 'completed' : 'failed',
                'bank_reference' => $result->transactionId,
                'processed_at' => now(),
            ]);

            // Update monthly billing status
            if ($result->success) {
                $billing->update([
                    'status' => 'paid',
                    'paid_at' => now(),
                ]);

                Log::info('Subscription fee processed successfully', [
                    'billing_id' => $billing->id,
                    'business_id' => $business->id,
                    'subscription_fee' => $billing->subscription_fee,
                    'transaction_id' => $result->transactionId,
                ]);
            } else {
                Log::error('Subscription fee processing failed', [
                    'billing_id' => $billing->id,
                    'business_id' => $business->id,
                    'subscription_fee' => $billing->subscription_fee,
                    'error' => $result->errorMessage,
                ]);
            }

            return $result->success;
        });
    }
}
