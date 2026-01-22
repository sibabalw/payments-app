<?php

use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create([
        'has_completed_dashboard_tour' => false,
        'dashboard_tour_completed_at' => null,
        'onboarding_completed_at' => now(),
    ]);
    $this->business = Business::factory()->create([
        'user_id' => $this->user->id,
        'status' => 'active',
    ]);
    $this->user->businesses()->attach($this->business->id, ['role' => 'owner']);
    $this->user->update(['current_business_id' => $this->business->id]);
});

it('requires authentication to complete the dashboard tour', function () {
    $response = $this->post('/dashboard/complete-tour');

    $response->assertRedirect('/login');
});

it('marks the dashboard tour as completed for authenticated user', function () {
    $response = $this->actingAs($this->user)
        ->post('/dashboard/complete-tour');

    $response->assertRedirect();

    $this->user->refresh();

    expect($this->user->has_completed_dashboard_tour)->toBeTrue();
    expect($this->user->dashboard_tour_completed_at)->not->toBeNull();
});

it('can complete the tour even if already completed', function () {
    $this->user->update([
        'has_completed_dashboard_tour' => true,
        'dashboard_tour_completed_at' => now()->subDay(),
    ]);

    $oldTimestamp = $this->user->dashboard_tour_completed_at;

    $response = $this->actingAs($this->user)
        ->post('/dashboard/complete-tour');

    $response->assertRedirect();

    $this->user->refresh();

    expect($this->user->has_completed_dashboard_tour)->toBeTrue();
    // Timestamp should be updated
    expect($this->user->dashboard_tour_completed_at->gt($oldTimestamp))->toBeTrue();
});

it('shares tour completion status via inertia', function () {
    $response = $this->actingAs($this->user)
        ->get('/dashboard');

    $response->assertInertia(fn ($page) => $page
        ->where('hasCompletedDashboardTour', false)
    );

    // Complete the tour
    $this->actingAs($this->user)
        ->post('/dashboard/complete-tour');

    // Check again
    $response = $this->actingAs($this->user)
        ->get('/dashboard');

    $response->assertInertia(fn ($page) => $page
        ->where('hasCompletedDashboardTour', true)
    );
});
