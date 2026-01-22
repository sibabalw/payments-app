<?php

use App\Models\Business;
use App\Models\PaymentSchedule;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();

    // Create two businesses for the user
    $this->businessA = Business::factory()->create([
        'user_id' => $this->user->id,
        'name' => 'Business A',
    ]);
    $this->businessB = Business::factory()->create([
        'user_id' => $this->user->id,
        'name' => 'Business B',
    ]);

    // Attach both businesses to user
    $this->user->businesses()->attach($this->businessA->id, ['role' => 'owner']);
    $this->user->businesses()->attach($this->businessB->id, ['role' => 'owner']);

    // Set initial business to A
    $this->user->update(['current_business_id' => $this->businessA->id]);
});

it('switches the current business', function () {
    $this->actingAs($this->user)
        ->post("/businesses/{$this->businessB->id}/switch")
        ->assertRedirect();

    // Refresh user from database
    $this->user->refresh();

    expect($this->user->current_business_id)->toBe($this->businessB->id);
});

it('updates session when switching business', function () {
    $this->actingAs($this->user)
        ->post("/businesses/{$this->businessB->id}/switch");

    expect(session('current_business_id'))->toBe($this->businessB->id);
});

it('prevents switching to a business the user does not have access to', function () {
    $otherBusiness = Business::factory()->create();

    $this->actingAs($this->user)
        ->post("/businesses/{$otherBusiness->id}/switch")
        ->assertRedirect()
        ->assertSessionHas('error');

    // Should remain on original business
    $this->user->refresh();
    expect($this->user->current_business_id)->toBe($this->businessA->id);
});

it('filters payment schedules by current business after switch', function () {
    // Create payment schedules for each business
    $scheduleA = PaymentSchedule::factory()->create([
        'business_id' => $this->businessA->id,
        'name' => 'Schedule A',
        'type' => 'generic',
    ]);
    $scheduleB = PaymentSchedule::factory()->create([
        'business_id' => $this->businessB->id,
        'name' => 'Schedule B',
        'type' => 'generic',
    ]);

    // Initially viewing with Business A
    $response = $this->actingAs($this->user)
        ->get('/payments');

    $response->assertInertia(fn ($page) => $page
        ->component('payments/index')
        ->has('schedules.data', 1)
        ->where('schedules.data.0.name', 'Schedule A')
    );

    // Switch to Business B
    $this->actingAs($this->user)
        ->post("/businesses/{$this->businessB->id}/switch");

    // Now should see Business B schedules
    $response = $this->actingAs($this->user)
        ->get('/payments');

    $response->assertInertia(fn ($page) => $page
        ->component('payments/index')
        ->has('schedules.data', 1)
        ->where('schedules.data.0.name', 'Schedule B')
    );
});

it('persists business selection across multiple requests', function () {
    // Switch to Business B
    $this->actingAs($this->user)
        ->post("/businesses/{$this->businessB->id}/switch");

    // Make multiple requests - all should use Business B
    $this->actingAs($this->user)
        ->get('/payments')
        ->assertOk();

    $this->user->refresh();
    expect($this->user->current_business_id)->toBe($this->businessB->id);

    $this->actingAs($this->user)
        ->get('/payroll')
        ->assertOk();

    $this->user->refresh();
    expect($this->user->current_business_id)->toBe($this->businessB->id);

    $this->actingAs($this->user)
        ->get('/dashboard')
        ->assertOk();

    $this->user->refresh();
    expect($this->user->current_business_id)->toBe($this->businessB->id);
});
