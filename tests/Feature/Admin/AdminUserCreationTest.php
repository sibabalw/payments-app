<?php

use App\Mail\AdminAddedEmail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

test('add admin page is only accessible by admins', function () {
    $user = User::factory()->create(['is_admin' => false]);

    $this->actingAs($user)
        ->get(route('admin.users.create'))
        ->assertForbidden();
});

test('admin can view add admin page', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('admin.users.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('admin/users/create'));
});

test('admin can create new administrator and notification email is sent', function () {
    Mail::fake();

    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)
        ->post(route('admin.users.store'), [
            'name' => 'New Admin',
            'email' => 'newadmin@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

    $response->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.users.index'))
        ->assertSessionHas('success');

    $user = User::query()->where('email', 'newadmin@example.com')->first();
    expect($user)->not->toBeNull()
        ->and($user->name)->toBe('New Admin')
        ->and($user->is_admin)->toBeTrue();

    Mail::assertQueued(AdminAddedEmail::class, function ($mail) {
        return $mail->hasTo('newadmin@example.com');
    });
});

test('store admin validates required fields', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)
        ->post(route('admin.users.store'), []);

    $response->assertSessionHasErrors(['name', 'email', 'password']);
});

test('store admin rejects duplicate email', function () {
    User::factory()->create(['email' => 'existing@example.com']);
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)
        ->post(route('admin.users.store'), [
            'name' => 'Duplicate',
            'email' => 'existing@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

    $response->assertSessionHasErrors(['email']);
});
