<?php

use App\Models\Business;
use App\Models\MonthlyBilling;
use App\Models\User;
use App\Services\BillingGateway\BillingGatewayFactory;
use App\Services\BillingGateway\MockBillingGateway;
use App\Services\BillingService;

test('mock billing gateway can charge subscription successfully', function () {
    $gateway = new MockBillingGateway(1.0); // 100% success rate
    $business = Business::factory()->create([
        'bank_account_details' => [
            'account_number' => '1234567890',
            'bank_name' => 'Test Bank',
            'account_holder_name' => 'Test Business',
            'account_type' => 'business',
        ],
    ]);

    $result = $gateway->chargeSubscription(1000.00, 'ZAR', $business);

    expect($result->success)->toBeTrue();
    expect($result->transactionId)->not->toBeNull();
    expect($result->errorMessage)->toBeNull();
});

test('mock billing gateway can fail subscription charge', function () {
    $gateway = new MockBillingGateway(0.0); // 0% success rate
    $business = Business::factory()->create([
        'bank_account_details' => [
            'account_number' => '1234567890',
            'bank_name' => 'Test Bank',
            'account_holder_name' => 'Test Business',
            'account_type' => 'business',
        ],
    ]);

    $result = $gateway->chargeSubscription(1000.00, 'ZAR', $business);

    expect($result->success)->toBeFalse();
    expect($result->transactionId)->toBeNull();
    expect($result->errorMessage)->not->toBeNull();
});

test('billing gateway factory creates mock gateway by default', function () {
    $gateway = BillingGatewayFactory::make();

    expect($gateway)->toBeInstanceOf(MockBillingGateway::class);
});

test('billing service processes subscription fee successfully', function () {
    $user = User::factory()->create();
    $business = Business::factory()->create([
        'user_id' => $user->id,
        'bank_account_details' => [
            'account_number' => '1234567890',
            'bank_name' => 'Test Bank',
            'account_holder_name' => 'Test Business',
            'account_type' => 'business',
        ],
    ]);

    $billing = MonthlyBilling::factory()->create([
        'business_id' => $business->id,
        'status' => 'pending',
        'subscription_fee' => 1000.00,
    ]);

    $billingService = new BillingService(new MockBillingGateway(1.0)); // 100% success rate

    $result = $billingService->processSubscriptionFee($business, $billing);

    expect($result)->toBeTrue();
    $billing->refresh();
    expect($billing->status)->toBe('paid');
    expect($billing->paid_at)->not->toBeNull();
});

test('billing service fails when business has no bank account details', function () {
    $user = User::factory()->create();
    $business = Business::factory()->create([
        'user_id' => $user->id,
        'bank_account_details' => null,
    ]);

    $billing = MonthlyBilling::factory()->create([
        'business_id' => $business->id,
        'status' => 'pending',
        'subscription_fee' => 1000.00,
    ]);

    $billingService = new BillingService;

    $result = $billingService->processSubscriptionFee($business, $billing);

    expect($result)->toBeFalse();
    $billing->refresh();
    expect($billing->status)->toBe('pending');
});

test('billing service creates billing transaction on charge', function () {
    $user = User::factory()->create();
    $business = Business::factory()->create([
        'user_id' => $user->id,
        'bank_account_details' => [
            'account_number' => '1234567890',
            'bank_name' => 'Test Bank',
            'account_holder_name' => 'Test Business',
            'account_type' => 'business',
        ],
    ]);

    $billing = MonthlyBilling::factory()->create([
        'business_id' => $business->id,
        'status' => 'pending',
        'subscription_fee' => 1000.00,
    ]);

    $billingService = new BillingService(new MockBillingGateway(1.0));

    $billingService->processSubscriptionFee($business, $billing);

    $transaction = \App\Models\BillingTransaction::where('monthly_billing_id', $billing->id)
        ->where('type', 'subscription_fee')
        ->first();

    expect($transaction)->not->toBeNull();
    expect($transaction->amount)->toBe(1000.00);
    expect($transaction->status)->toBe('completed');
    expect($transaction->bank_reference)->not->toBeNull();
});
