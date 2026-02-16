<?php

namespace App\Http\Controllers;

use App\Http\Requests\ContactFormRequest;
use App\Mail\ContactFormSubmittedEmail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

class ContactController extends Controller
{
    /**
     * Handle the contact form submission.
     */
    public function store(ContactFormRequest $request): \Illuminate\Http\RedirectResponse
    {
        $validated = $request->validated();

        $admins = User::query()->where('is_admin', true)->get();

        $mailable = new ContactFormSubmittedEmail(
            $validated['name'],
            $validated['email'],
            $validated['message']
        );

        if ($admins->isNotEmpty()) {
            Mail::to($admins)->send($mailable);
        } else {
            Mail::to(config('mail.from.address'))->send($mailable);
        }

        return back()->with('success', 'Thank you for your message. We will get back to you soon.');
    }
}
