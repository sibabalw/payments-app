<?php

use App\Mail\ContactFormSubmittedEmail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

test('contact form submission sends email to admins and redirects with success', function () {
    Mail::fake();

    $admin1 = User::factory()->admin()->create(['email' => 'admin1@example.com']);
    $admin2 = User::factory()->admin()->create(['email' => 'admin2@example.com']);

    $response = $this->post(route('contact.store'), [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'message' => 'I would like to learn more about SwiftPay.',
    ]);

    $response->assertRedirect(route('contact'));
    $response->assertSessionHas('success');

    Mail::assertSent(ContactFormSubmittedEmail::class, 2);
    Mail::assertSent(ContactFormSubmittedEmail::class, function (ContactFormSubmittedEmail $mail) use ($admin1) {
        return $mail->hasTo($admin1->email)
            && $mail->senderName === 'Jane Doe'
            && $mail->senderEmail === 'jane@example.com'
            && $mail->message === 'I would like to learn more about SwiftPay.';
    });
    Mail::assertSent(ContactFormSubmittedEmail::class, function (ContactFormSubmittedEmail $mail) use ($admin2) {
        return $mail->hasTo($admin2->email);
    });
});

test('contact form sends to default address when no admins exist', function () {
    Mail::fake();

    $response = $this->post(route('contact.store'), [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'message' => 'Hello',
    ]);

    $response->assertRedirect(route('contact'));
    Mail::assertSent(ContactFormSubmittedEmail::class, function (ContactFormSubmittedEmail $mail) {
        return $mail->hasTo(config('mail.from.address'));
    });
});

test('contact form validation fails for missing name', function () {
    Mail::fake();

    $response = $this->post(route('contact.store'), [
        'name' => '',
        'email' => 'jane@example.com',
        'message' => 'Hello',
    ]);

    $response->assertSessionHasErrors('name');
    Mail::assertNotSent(ContactFormSubmittedEmail::class);
});

test('contact form validation fails for invalid email', function () {
    Mail::fake();

    $response = $this->post(route('contact.store'), [
        'name' => 'Jane Doe',
        'email' => 'not-an-email',
        'message' => 'Hello',
    ]);

    $response->assertSessionHasErrors('email');
    Mail::assertNotSent(ContactFormSubmittedEmail::class);
});

test('contact form validation fails for missing message', function () {
    Mail::fake();

    $response = $this->post(route('contact.store'), [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'message' => '',
    ]);

    $response->assertSessionHasErrors('message');
    Mail::assertNotSent(ContactFormSubmittedEmail::class);
});
