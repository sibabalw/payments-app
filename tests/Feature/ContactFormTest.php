<?php

use App\Mail\ContactFormSubmittedEmail;
use Illuminate\Support\Facades\Mail;

test('contact form submission sends email to info address and redirects with success', function () {
    Mail::fake();

    $response = $this->post(route('contact.store'), [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'message' => 'I would like to learn more about SwiftPay.',
    ]);

    $response->assertRedirect(route('contact'));
    $response->assertSessionHas('success');

    Mail::assertSent(ContactFormSubmittedEmail::class, function (ContactFormSubmittedEmail $mail) {
        return $mail->hasTo(config('mail.from.address'))
            && $mail->senderName === 'Jane Doe'
            && $mail->senderEmail === 'jane@example.com'
            && $mail->messageContent === 'I would like to learn more about SwiftPay.';
    });
});

test('contact form sends to config mail from address', function () {
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
