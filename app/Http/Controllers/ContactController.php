<?php

namespace App\Http\Controllers;

use App\Http\Requests\ContactFormRequest;
use App\Mail\ContactFormSubmittedEmail;
use Illuminate\Support\Facades\Mail;

class ContactController extends Controller
{
    /**
     * Handle the contact form submission.
     */
    public function store(ContactFormRequest $request): \Illuminate\Http\RedirectResponse
    {
        $validated = $request->validated();

        $mailable = new ContactFormSubmittedEmail(
            $validated['name'],
            $validated['email'],
            $validated['message']
        );

        Mail::to(config('mail.from.address'))->send($mailable);

        return redirect()->route('contact')->with('success', 'Thank you for your message. We will get back to you soon.');
    }
}
