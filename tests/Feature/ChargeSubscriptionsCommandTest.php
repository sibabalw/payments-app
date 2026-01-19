<?php

use App\Models\Business;
use App\Models\MonthlyBilling;
use App\Models\User;
use App\Services\BillingGateway\MockBillingGateway;
use App\Services\BillingService;
use Illuminate\Support\Facades\Artisan;

test('charge subscriptions command processes pending billings', function () {
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

    $month = now()->subMonth()->format('Y-m');
    $billing = MonthlyBilling::factory()->create([
        'business_id' => $business->id,
        'billing_month' => $month,
        'status' => 'pending',
        'subscription_fee' => 1000.00,
    ]);

    // Mock the billing service to use 100% success rate
    $this->app->bind(BillingService::class, function () {
        return new BillingService(new MockBillingGateway(1.0));
    });

    Artisan::call('billing:charge-subscriptions', [
        '--month' => $month,
    ]);

    $billing->refresh();
    expect($billing->status)->toBe('paid');
    expect(Artisan::output())->toContain('Succeeded: 1');
});

test('charge subscriptions command skips businesses without bank account details', function () {
    $user = User::factory()->create();
    $business = Business::factory()->create([
        'user_id' => $user->id,
        'bank_account_details' => null,
    ]);

    $month = now()->subMonth()->format('Y-m');
    $billing = MonthlyBilling::factory()->create([
        'business_id' => $business->id,
        'billing_month' => $month,
        'status' => 'pending',
        'subscription_fee' => 1000.00,
    ]);

    Artisan::call('billing:charge-subscriptions', [
        '--month' => $month,
    ]);

    $billing->refresh();
    expect($billing->status)->toBe('pending');
    expect(Artisan::output())->toContain('Skipped: 1');
});

test('charge subscriptions command can filter by business id', function () {
    $user = User::factory()->create();
    $business1 = Business::factory()->create([
        'user_id' => $user->id,
        'bank_account_details' => [
            'account_number' => '1234567890',
            'bank_name' => 'Test Bank',
            'account_holder_name' => 'Test Business',
            'account_type' => 'business',
        ],
    ]);
    $business2 = Business::factory()->create([
        'user_id' => $user->id,
        'bank_account_details' => [
            'account_number' => '0987654321',
            'bank_name' => 'Test Bank',
            'account_holder_name' => 'Test Business 2',
            'account_type' => 'business',
        ],
    ]);

    $month = now()->subMonth()->format('Y-m');
    $billing1 = MonthlyBilling::factory()->create([
        'business_id' => $business1->id,
        'billing_month' => $month,
        'status' => 'pending',
        'subscription_fee' => 1000.00,
    ]);
    $billing2 = MonthlyBilling::factory()->create([
        'business_id' => $business2->id,
        'billing_month' => $month,
        'status' => 'pending',
        'subscription_fee' => 1000.00,
    ]);

    $this->app->bind(BillingService::class, function () {
        return new BillingService(new MockBillingGateway(1.0));
    });

    Artisan::call('billing:charge-subscriptions', [
        '--month' => $month,
        '--business' => $business1->id,
    ]);

    $billing1->refresh();
    $billing2->refresh();
    expect($billing1->status)->toBe('paid');
    expect($billing2->status)->toBe('pending');
});
