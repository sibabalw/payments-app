<?php

use App\Models\User;

test('admin can access account profile page', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)
        ->get(route('admin.account.profile.edit'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page->component('admin/account/profile'));
});

test('admin can access account password page', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)
        ->get(route('admin.account.password.edit'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page->component('admin/account/password'));
});

test('admin can access account appearance page', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)
        ->get(route('admin.account.appearance.edit'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page->component('admin/account/appearance'));
});

test('admin can access account two-factor page', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)
        ->get(route('admin.account.two-factor.show'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page->component('admin/account/two-factor'));
});

test('admin account redirects to profile', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)
        ->get(route('admin.account.redirect'));

    $response->assertRedirect('/admin/account/profile');
});

test('admin profile update redirects to admin account profile', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)
        ->patch(route('admin.account.profile.update'), [
            'name' => 'Updated Admin',
            'email' => $admin->email,
        ]);

    $response->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.account.profile.edit'));

    expect($admin->refresh()->name)->toBe('Updated Admin');
});
