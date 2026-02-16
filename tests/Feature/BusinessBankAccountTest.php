<?php

use App\Models\Business;
use App\Models\User;

test('business can view bank account page', function () {
    $user = User::factory()->create();
    $business = Business::factory()->create([
        'user_id' => $user->id,
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('businesses.bank-account.edit', $business));

    $response->assertOk();
});

test('business can update bank account details', function () {
    $user = User::factory()->create();
    $business = Business::factory()->create([
        'user_id' => $user->id,
    ]);

    $bankAccountDetails = [
        'account_number' => '1234567890',
        'bank_name' => 'Test Bank',
        'account_holder_name' => 'Test Business',
        'account_type' => 'business',
        'branch_code' => '123456',
    ];

    $response = $this
        ->actingAs($user)
        ->put(route('businesses.bank-account.update', $business), [
            'bank_account_details' => $bankAccountDetails,
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('businesses.bank-account.edit', $business));

    $business->refresh();
    expect($business->bank_account_details)->toBe($bankAccountDetails);
});

test('business bank account update requires valid data', function () {
    $user = User::factory()->create();
    $business = Business::factory()->create([
        'user_id' => $user->id,
    ]);

    $response = $this
        ->actingAs($user)
        ->put(route('businesses.bank-account.update', $business), [
            'bank_account_details' => [
                'account_number' => '', // Missing required field
            ],
        ]);

    $response->assertSessionHasErrors(['bank_account_details.account_number']);
});

test('business hasBankAccountDetails helper works correctly', function () {
    $business = Business::factory()->create([
        'bank_account_details' => null,
    ]);

    expect($business->hasBankAccountDetails())->toBeFalse();

    $business->update([
        'bank_account_details' => [
            'account_number' => '1234567890',
            'bank_name' => 'Test Bank',
        ],
    ]);

    $business->refresh();
    expect($business->hasBankAccountDetails())->toBeTrue();
});

test('unauthorized user cannot update bank account details', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $business = Business::factory()->create([
        'user_id' => $owner->id,
    ]);

    $response = $this
        ->actingAs($otherUser)
        ->put(route('businesses.bank-account.update', $business), [
            'bank_account_details' => [
                'account_number' => '1234567890',
                'bank_name' => 'Test Bank',
                'account_holder_name' => 'Test Business',
                'account_type' => 'business',
            ],
        ]);

    $response->assertForbidden();
});
