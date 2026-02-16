<?php

use App\Models\Business;
use App\Models\User;

test('guests cannot access admin dashboard', function () {
    $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
});

test('regular users cannot access admin dashboard', function () {
    $user = User::factory()->create(['is_admin' => false]);

    $this->actingAs($user)
        ->get(route('admin.dashboard'))
        ->assertForbidden();
});

test('admin users can access admin dashboard', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/dashboard/index')
            ->has('businessMetrics')
            ->has('userMetrics')
            ->has('paymentJobMetrics')
            ->has('payrollJobMetrics')
            ->has('totalEscrowBalance')
            ->has('recentBusinesses')
            ->has('recentStatusChanges')
        );
});

test('admin dashboard shows correct business metrics', function () {
    $admin = User::factory()->admin()->create();

    // Create businesses with different statuses
    Business::factory()->count(3)->create(['status' => 'active']);
    Business::factory()->count(2)->create(['status' => 'suspended']);
    Business::factory()->count(1)->create(['status' => 'banned']);

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('businessMetrics.total', 6)
            ->where('businessMetrics.active', 3)
            ->where('businessMetrics.suspended', 2)
            ->where('businessMetrics.banned', 1)
        );
});

test('admin dashboard shows correct user metrics', function () {
    $admin = User::factory()->admin()->create();

    // Create regular users
    User::factory()->count(5)->create(['is_admin' => false]);
    User::factory()->count(2)->admin()->create();

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('userMetrics.total', 8) // 5 regular + 2 admin + 1 acting admin
            ->where('userMetrics.admins', 3) // 2 + 1 acting admin
        );
});
