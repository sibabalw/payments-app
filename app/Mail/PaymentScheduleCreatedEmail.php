<?php

namespace App\Mail;

use App\Models\PaymentSchedule;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaymentScheduleCreatedEmail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public User $user,
        public PaymentSchedule $paymentSchedule
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        // Load business relationship
        $this->paymentSchedule->loadMissing('business');
        $business = $this->paymentSchedule->business;

        // Get business email and name, fallback to Swift Pay defaults
        $fromEmail = $business->email ?? config('mail.from.address');
        $fromName = $business->name ?? config('mail.from.name');

        return new Envelope(
            from: new Address($fromEmail, $fromName),
            subject: 'Payment Schedule Created: '.$this->paymentSchedule->name,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.payment-schedule-created',
            with: [
                'user' => $this->user,
                'paymentSchedule' => $this->paymentSchedule,
            ],
        );
    }
}
