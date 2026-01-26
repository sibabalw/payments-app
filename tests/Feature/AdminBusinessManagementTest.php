<?php

use App\Models\Business;
use App\Models\User;

test('guests cannot access admin businesses page', function () {
    $this->get(route('admin.businesses.index'))->assertRedirect(route('login'));
});

test('regular users cannot access admin businesses page', function () {
    $user = User::factory()->create(['is_admin' => false]);

    $this->actingAs($user)
        ->get(route('admin.businesses.index'))
        ->assertForbidden();
});

test('admin users can access admin businesses page', function () {
    $admin = User::factory()->admin()->create();
    Business::factory()->count(3)->create();

    $this->actingAs($admin)
        ->get(route('admin.businesses.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/businesses/index')
            ->has('businesses')
            ->has('statusCounts')
            ->has('filters')
        );
});

test('admin can filter businesses by status', function () {
    $admin = User::factory()->admin()->create();

    Business::factory()->count(3)->create(['status' => 'active']);
    Business::factory()->count(2)->create(['status' => 'suspended']);

    $this->actingAs($admin)
        ->get(route('admin.businesses.index', ['status' => 'active']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('businesses.data', 3)
        );

    $this->actingAs($admin)
        ->get(route('admin.businesses.index', ['status' => 'suspended']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('businesses.data', 2)
        );
});

test('admin can search businesses by name', function () {
    $admin = User::factory()->admin()->create();

    Business::factory()->create(['name' => 'Acme Corporation']);
    Business::factory()->create(['name' => 'Test Business']);
    Business::factory()->create(['name' => 'Another Company']);

    $this->actingAs($admin)
        ->get(route('admin.businesses.index', ['search' => 'Acme']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('businesses.data', 1)
            ->where('businesses.data.0.name', 'Acme Corporation')
        );
});

test('regular users cannot update business status', function () {
    $user = User::factory()->create(['is_admin' => false]);
    $business = Business::factory()->create(['status' => 'active']);

    $this->actingAs($user)
        ->post(route('admin.businesses.status', $business), [
            'status' => 'suspended',
            'reason' => 'Test reason',
        ])
        ->assertForbidden();

    expect($business->fresh()->status)->toBe('active');
});

test('admin can suspend a business', function () {
    $admin = User::factory()->admin()->create();
    $business = Business::factory()->create(['status' => 'active']);

    $this->actingAs($admin)
        ->post(route('admin.businesses.status', $business), [
            'status' => 'suspended',
            'reason' => 'Violating terms of service',
        ])
        ->assertRedirect();

    $business->refresh();
    expect($business->status)->toBe('suspended');
    expect($business->status_reason)->toBe('Violating terms of service');
    expect($business->status_changed_at)->not->toBeNull();
});

test('admin can ban a business', function () {
    $admin = User::factory()->admin()->create();
    $business = Business::factory()->create(['status' => 'active']);

    $this->actingAs($admin)
        ->post(route('admin.businesses.status', $business), [
            'status' => 'banned',
            'reason' => 'Fraudulent activity detected',
        ])
        ->assertRedirect();

    $business->refresh();
    expect($business->status)->toBe('banned');
    expect($business->status_reason)->toBe('Fraudulent activity detected');
});

test('admin can reactivate a suspended business', function () {
    $admin = User::factory()->admin()->create();
    $business = Business::factory()->create([
        'status' => 'suspended',
        'status_reason' => 'Previous violation',
    ]);

    $this->actingAs($admin)
        ->post(route('admin.businesses.status', $business), [
            'status' => 'active',
            'reason' => 'Issue resolved',
        ])
        ->assertRedirect();

    $business->refresh();
    expect($business->status)->toBe('active');
    expect($business->status_reason)->toBe('Issue resolved');
});

test('admin cannot set business to same status', function () {
    $admin = User::factory()->admin()->create();
    $business = Business::factory()->create(['status' => 'active']);

    $this->actingAs($admin)
        ->post(route('admin.businesses.status', $business), [
            'status' => 'active',
        ])
        ->assertRedirect()
        ->assertSessionHas('info');

    // Status should remain unchanged
    expect($business->fresh()->status)->toBe('active');
});

test('business status update requires valid status', function () {
    $admin = User::factory()->admin()->create();
    $business = Business::factory()->create(['status' => 'active']);

    $this->actingAs($admin)
        ->post(route('admin.businesses.status', $business), [
            'status' => 'invalid_status',
        ])
        ->assertSessionHasErrors('status');
});

test('business status update reason is optional', function () {
    $admin = User::factory()->admin()->create();
    $business = Business::factory()->create(['status' => 'active']);

    $this->actingAs($admin)
        ->post(route('admin.businesses.status', $business), [
            'status' => 'suspended',
        ])
        ->assertRedirect();

    $business->refresh();
    expect($business->status)->toBe('suspended');
    expect($business->status_reason)->toBeNull();
});
