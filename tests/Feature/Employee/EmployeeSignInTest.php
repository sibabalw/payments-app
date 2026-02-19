<?php

use App\Models\Business;
use App\Models\Employee;
use App\Services\EmployeeOtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

uses(RefreshDatabase::class);

test('employee sign-in page can be rendered', function () {
    $response = $this->get(route('employee.sign-in'));

    $response->assertStatus(200);
});

test('employee can request OTP with valid email', function () {
    Mail::fake();

    $business = Business::factory()->create();
    $employee = Employee::factory()->create([
        'business_id' => $business->id,
        'email' => 'employee@example.com',
    ]);

    $response = $this->post(route('employee.send-otp'), [
        'email' => 'employee@example.com',
    ]);

    $response->assertRedirect(route('employee.sign-in'));
    $response->assertSessionHas('otp_sent', true);
    $response->assertSessionHas('otp_email', 'employee@example.com');
    $response->assertSessionHas('status');

    Mail::assertSent(\App\Mail\EmployeeOtpEmail::class, function ($mail) use ($employee) {
        return $mail->employee->id === $employee->id;
    });
});

test('employee cannot request OTP with invalid email', function () {
    $response = $this->post(route('employee.send-otp'), [
        'email' => 'nonexistent@example.com',
    ]);

    $response->assertSessionHasErrors(['email']);
});

test('employee cannot request OTP with malformed email', function () {
    $response = $this->post(route('employee.send-otp'), [
        'email' => 'not-an-email',
    ]);

    $response->assertSessionHasErrors(['email']);
});

test('OTP is generated and stored in cache', function () {
    Mail::fake();

    $business = Business::factory()->create();
    $employee = Employee::factory()->create([
        'business_id' => $business->id,
        'email' => 'employee@example.com',
    ]);

    $otpService = app(EmployeeOtpService::class);
    $otp = $otpService->generateOtp('employee@example.com');

    expect($otp)->toMatch('/^\d{6}$/');
    expect(Cache::has('employee_otp:'.md5('employee@example.com')))->toBeTrue();
});

test('employee can verify valid OTP', function () {
    $business = Business::factory()->create();
    $employee = Employee::factory()->create([
        'business_id' => $business->id,
        'email' => 'employee@example.com',
    ]);

    $otpService = app(EmployeeOtpService::class);
    $otp = $otpService->generateOtp('employee@example.com');

    $response = $this->post(route('employee.verify-otp'), [
        'email' => 'employee@example.com',
        'otp' => $otp,
    ]);

    $response->assertRedirect(route('employee.time-tracking'));
    $response->assertSessionHas('employee_verified_id', $employee->id);
    $response->assertSessionHas('employee_verified_at');
    $response->assertSessionHas('employee_verified_email', 'employee@example.com');
});

test('employee cannot verify invalid OTP', function () {
    $business = Business::factory()->create();
    $employee = Employee::factory()->create([
        'business_id' => $business->id,
        'email' => 'employee@example.com',
    ]);

    $otpService = app(EmployeeOtpService::class);
    $otpService->generateOtp('employee@example.com');

    $response = $this->post(route('employee.verify-otp'), [
        'email' => 'employee@example.com',
        'otp' => '000000',
    ]);

    $response->assertSessionHasErrors(['otp']);
    expect($response->session()->has('employee_verified_id'))->toBeFalse();
});

test('employee cannot verify expired OTP', function () {
    $business = Business::factory()->create();
    $employee = Employee::factory()->create([
        'business_id' => $business->id,
        'email' => 'employee@example.com',
    ]);

    $otpService = app(EmployeeOtpService::class);
    $otp = $otpService->generateOtp('employee@example.com');

    // Expire the OTP by clearing cache
    Cache::forget('employee_otp:'.md5('employee@example.com'));

    $response = $this->post(route('employee.verify-otp'), [
        'email' => 'employee@example.com',
        'otp' => $otp,
    ]);

    $response->assertSessionHasErrors(['otp']);
});

test('OTP can only be used once', function () {
    $business = Business::factory()->create();
    $employee = Employee::factory()->create([
        'business_id' => $business->id,
        'email' => 'employee@example.com',
    ]);

    $otpService = app(EmployeeOtpService::class);
    $otp = $otpService->generateOtp('employee@example.com');

    // First verification should succeed
    $response = $this->post(route('employee.verify-otp'), [
        'email' => 'employee@example.com',
        'otp' => $otp,
    ]);

    $response->assertRedirect(route('employee.time-tracking'));

    // Second verification should fail (OTP already consumed)
    $response = $this->post(route('employee.verify-otp'), [
        'email' => 'employee@example.com',
        'otp' => $otp,
    ]);

    $response->assertSessionHasErrors(['otp']);
});

test('verified employee can access time tracking page', function () {
    $business = Business::factory()->create();
    $employee = Employee::factory()->create([
        'business_id' => $business->id,
        'email' => 'employee@example.com',
    ]);

    $otpService = app(EmployeeOtpService::class);
    $otp = $otpService->generateOtp('employee@example.com');

    // Verify OTP first
    $this->post(route('employee.verify-otp'), [
        'email' => 'employee@example.com',
        'otp' => $otp,
    ]);

    $response = $this->get(route('employee.time-tracking'));

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->component('employee/time-tracking')
        ->has('employee')
        ->where('employee.id', $employee->id)
    );
});

test('unverified employee cannot access time tracking page', function () {
    $response = $this->get(route('employee.time-tracking'));

    $response->assertRedirect(route('employee.sign-in'));
    $response->assertSessionHas('error');
});

test('verified employee can sign in for time tracking', function () {
    $business = Business::factory()->create();
    $employee = Employee::factory()->create([
        'business_id' => $business->id,
        'email' => 'employee@example.com',
    ]);

    $otpService = app(EmployeeOtpService::class);
    $otp = $otpService->generateOtp('employee@example.com');

    // Verify OTP first
    $this->post(route('employee.verify-otp'), [
        'email' => 'employee@example.com',
        'otp' => $otp,
    ]);

    $response = $this->post(route('employee.time-tracking.sign-in'));

    $response->assertRedirect(route('employee.time-tracking'));
    $response->assertSessionHas('success');

    $this->assertDatabaseHas('time_entries', [
        'employee_id' => $employee->id,
        'date' => today()->format('Y-m-d'),
        'entry_type' => 'digital',
        'created_by' => null,
    ]);
});

test('verified employee can sign out for time tracking', function () {
    $business = Business::factory()->create();
    $employee = Employee::factory()->create([
        'business_id' => $business->id,
        'email' => 'employee@example.com',
    ]);

    $otpService = app(EmployeeOtpService::class);
    $otp = $otpService->generateOtp('employee@example.com');

    // Verify OTP first
    $this->post(route('employee.verify-otp'), [
        'email' => 'employee@example.com',
        'otp' => $otp,
    ]);

    // Sign in first
    $this->post(route('employee.time-tracking.sign-in'));

    // Then sign out
    $response = $this->post(route('employee.time-tracking.sign-out'));

    $response->assertRedirect(route('employee.time-tracking'));
    $response->assertSessionHas('success');

    $this->assertDatabaseHas('time_entries', [
        'employee_id' => $employee->id,
        'date' => today()->format('Y-m-d'),
    ]);

    $entry = \App\Models\TimeEntry::where('employee_id', $employee->id)
        ->where('date', today()->format('Y-m-d'))
        ->first();

    expect($entry->sign_out_time)->not->toBeNull();
});

test('employee cannot sign in twice in the same day', function () {
    $business = Business::factory()->create();
    $employee = Employee::factory()->create([
        'business_id' => $business->id,
        'email' => 'employee@example.com',
    ]);

    $otpService = app(EmployeeOtpService::class);
    $otp = $otpService->generateOtp('employee@example.com');

    // Verify OTP first
    $this->post(route('employee.verify-otp'), [
        'email' => 'employee@example.com',
        'otp' => $otp,
    ]);

    // Sign in first time
    $this->post(route('employee.time-tracking.sign-in'));

    // Try to sign in again
    $response = $this->post(route('employee.time-tracking.sign-in'));

    $response->assertSessionHasErrors(['error']);
});

test('employee cannot sign out if not signed in', function () {
    $business = Business::factory()->create();
    $employee = Employee::factory()->create([
        'business_id' => $business->id,
        'email' => 'employee@example.com',
    ]);

    $otpService = app(EmployeeOtpService::class);
    $otp = $otpService->generateOtp('employee@example.com');

    // Verify OTP first
    $this->post(route('employee.verify-otp'), [
        'email' => 'employee@example.com',
        'otp' => $otp,
    ]);

    // Try to sign out without signing in
    $response = $this->post(route('employee.time-tracking.sign-out'));

    $response->assertSessionHasErrors(['error']);
});

test('OTP generation is rate limited', function () {
    Mail::fake();

    $business = Business::factory()->create();
    $employee = Employee::factory()->create([
        'business_id' => $business->id,
        'email' => 'employee@example.com',
    ]);

    $otpService = app(EmployeeOtpService::class);

    // Generate 5 OTPs (rate limit)
    for ($i = 0; $i < 5; $i++) {
        $otpService->generateOtp('employee@example.com');
    }

    // 6th attempt should be rate limited
    $key = 'employee_otp_generate:employee@example.com';
    $remaining = RateLimiter::remaining($key, 5);

    expect($remaining)->toBe(0);
});

test('session expires after 24 hours', function () {
    $business = Business::factory()->create();
    $employee = Employee::factory()->create([
        'business_id' => $business->id,
        'email' => 'employee@example.com',
    ]);

    $otpService = app(EmployeeOtpService::class);
    $otp = $otpService->generateOtp('employee@example.com');

    // Verify OTP
    $this->post(route('employee.verify-otp'), [
        'email' => 'employee@example.com',
        'otp' => $otp,
    ]);

    // Set session to expired (25 hours ago)
    $this->withSession([
        'employee_verified_id' => $employee->id,
        'employee_verified_at' => now()->subHours(25)->timestamp,
        'employee_verified_email' => 'employee@example.com',
    ]);

    $response = $this->get(route('employee.time-tracking'));

    $response->assertRedirect(route('employee.sign-in'));
    $response->assertSessionHas('error');
});

test('case insensitive email matching works', function () {
    Mail::fake();

    $business = Business::factory()->create();
    $employee = Employee::factory()->create([
        'business_id' => $business->id,
        'email' => 'Employee@Example.com',
    ]);

    $otpService = app(EmployeeOtpService::class);
    $otp = $otpService->generateOtp('EMPLOYEE@EXAMPLE.COM');

    $response = $this->post(route('employee.verify-otp'), [
        'email' => 'employee@example.com',
        'otp' => $otp,
    ]);

    $response->assertRedirect(route('employee.time-tracking'));
});

test('case insensitive OTP verification works', function () {
    $business = Business::factory()->create();
    $employee = Employee::factory()->create([
        'business_id' => $business->id,
        'email' => 'employee@example.com',
    ]);

    $otpService = app(EmployeeOtpService::class);
    $otp = $otpService->generateOtp('employee@example.com');

    // OTP should be numeric, but test case insensitivity for email
    $response = $this->post(route('employee.verify-otp'), [
        'email' => 'EMPLOYEE@EXAMPLE.COM',
        'otp' => $otp,
    ]);

    $response->assertRedirect(route('employee.time-tracking'));
});
