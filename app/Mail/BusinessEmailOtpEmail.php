<?php

namespace App\Mail;

use App\Models\Business;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BusinessEmailOtpEmail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public Business $business,
        public string $newEmail,
        public string $otp
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        // Load business relationship
        $business = $this->business;

        // Get business email and name, fallback to SwiftPay defaults
        $fromEmail = $business->email ?? config('mail.from.address');
        $fromName = $business->name ?? config('mail.from.name');

        return new Envelope(
            from: new Address($fromEmail, $fromName),
            subject: 'Verify Your New Business Email Address',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        // Load business relationship
        $business = $this->business;

        return new Content(
            view: 'emails.business-email-otp',
            with: [
                'business' => $business,
                'newEmail' => $this->newEmail,
                'otp' => $this->otp,
                'user' => (object) ['email' => $this->newEmail], // For email layout compatibility
            ],
        );
    }
}
