<?php

use App\Mail\AdminOtpEmail;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Laravel\Socialite\Facades\Socialite;

test('admin password login redirects to OTP page and sends OTP email', function () {
    Mail::fake();

    $admin = User::factory()->admin()->create();

    $response = $this->post(route('login.store'), [
        'email' => $admin->email,
        'password' => 'password',
    ]);

    $response->assertRedirect(route('admin.otp.show'));
    $response->assertSessionHas('status');
    $response->assertSessionHas('admin_otp_pending');
    $this->assertGuest();

    Mail::assertSent(AdminOtpEmail::class, function (AdminOtpEmail $mail) use ($admin) {
        return $mail->hasTo($admin->email) && strlen($mail->otp) === 6;
    });
});

test('admin who has Google account still receives OTP when logging in with password', function () {
    Mail::fake();

    $admin = User::factory()->admin()->create([
        'google_id' => 'google-123',
    ]);

    $response = $this->post(route('login.store'), [
        'email' => $admin->email,
        'password' => 'password',
    ]);

    $response->assertRedirect(route('admin.otp.show'));
    $response->assertSessionHas('admin_otp_pending');
    $this->assertGuest();
    Mail::assertSent(AdminOtpEmail::class, function (AdminOtpEmail $mail) use ($admin) {
        return $mail->hasTo($admin->email) && strlen($mail->otp) === 6;
    });
});

test('admin can complete login after entering valid OTP', function () {
    Mail::fake();

    $admin = User::factory()->admin()->create();

    $this->post(route('login.store'), [
        'email' => $admin->email,
        'password' => 'password',
    ]);

    $otp = null;
    Mail::assertSent(AdminOtpEmail::class, function (AdminOtpEmail $mail) use (&$otp) {
        $otp = $mail->otp;

        return true;
    });
    $this->assertNotNull($otp);

    $response = $this->post(route('admin.otp.verify'), [
        'otp' => $otp,
    ]);

    $response->assertRedirect(route('admin.dashboard'));
    $response->assertSessionMissing('admin_otp_pending');
    $this->assertAuthenticatedAs($admin);
});

test('invalid OTP keeps user on OTP page and does not log in', function () {
    Mail::fake();

    $admin = User::factory()->admin()->create();

    $this->post(route('login.store'), [
        'email' => $admin->email,
        'password' => 'password',
    ]);

    $response = $this->post(route('admin.otp.verify'), [
        'otp' => '000000',
    ]);

    $response->assertRedirect(route('admin.otp.show'));
    $response->assertSessionHas('error');
    $this->assertGuest();
});

test('expired or missing pending session redirects to login', function () {
    $response = $this->get(route('admin.otp.show'));

    $response->assertRedirect(route('login'));
    $response->assertSessionHas('error');
});

test('verify without pending session redirects to login', function () {
    $response = $this->post(route('admin.otp.verify'), [
        'otp' => '123456',
    ]);

    $response->assertRedirect(route('login'));
    $response->assertSessionHas('error');
    $this->assertGuest();
});

test('non-admin login does not require OTP and redirects to dashboard', function () {
    Mail::fake();

    $user = User::factory()->create(['is_admin' => false]);

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticatedAs($user);
    $response->assertRedirect(config('fortify.home'));
    Mail::assertNotSent(AdminOtpEmail::class);
});

test('admin can resend OTP when pending session is valid', function () {
    Mail::fake();

    $admin = User::factory()->admin()->create();

    $this->post(route('login.store'), [
        'email' => $admin->email,
        'password' => 'password',
    ]);

    Mail::assertSentCount(1);

    $response = $this->post(route('admin.otp.resend'));

    $response->assertRedirect(route('admin.otp.show'));
    $response->assertSessionHas('status');
    Mail::assertSentCount(2);
});

test('resend without pending session redirects to login', function () {
    $response = $this->post(route('admin.otp.resend'));

    $response->assertRedirect(route('login'));
    $response->assertSessionHas('error');
});

test('admin OTP show page renders when session has valid pending', function () {
    $admin = User::factory()->admin()->create();
    $payload = [
        'user_id' => $admin->id,
        'email' => $admin->email,
        'expires_at' => now()->addMinutes(10)->timestamp,
    ];
    $encrypted = Crypt::encryptString(json_encode($payload));

    $response = $this->withSession(['admin_otp_pending' => $encrypted])
        ->get(route('admin.otp.show'));

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->component('admin/otp-challenge')
        ->has('email')
    );
});

test('admin OTP show redirects to login when pending is expired', function () {
    $admin = User::factory()->admin()->create();
    $payload = [
        'user_id' => $admin->id,
        'email' => $admin->email,
        'expires_at' => now()->subMinute()->timestamp,
    ];
    $encrypted = Crypt::encryptString(json_encode($payload));

    $response = $this->withSession(['admin_otp_pending' => $encrypted])
        ->get(route('admin.otp.show'));

    $response->assertRedirect(route('login'));
    $response->assertSessionHas('error');
});

test('admin signing in with Google does not require OTP and redirects to admin dashboard', function () {
    Mail::fake();

    $admin = User::factory()->admin()->create([
        'email' => 'admin@example.com',
        'google_id' => null,
    ]);

    $abstractUser = new \Laravel\Socialite\Two\User;
    $abstractUser->id = 'google-123';
    $abstractUser->email = 'admin@example.com';
    $abstractUser->name = 'Admin User';
    $abstractUser->avatar = null;
    $abstractUser->setToken('token');
    $abstractUser->setRefreshToken('refresh');
    $abstractUser->setExpiresIn(3600);

    $provider = $this->mock(\Laravel\Socialite\Contracts\Provider::class);
    $provider->shouldReceive('user')->andReturn($abstractUser);
    Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

    $response = $this->get(route('google.callback'));

    $response->assertRedirect(route('admin.dashboard'));
    $this->assertAuthenticatedAs($admin);
    Mail::assertNotSent(AdminOtpEmail::class);
});
