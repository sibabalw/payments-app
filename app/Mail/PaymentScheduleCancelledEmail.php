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

class PaymentScheduleCancelledEmail extends Mailable
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

        // Get business email and name, fallback to SwiftPay defaults
        $fromEmail = $business->email ?? config('mail.from.address');
        $fromName = $business->name ?? config('mail.from.name');

        return new Envelope(
            from: new Address($fromEmail, $fromName),
            subject: 'Payment Schedule Cancelled: '.$this->paymentSchedule->name,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.payment-schedule-cancelled',
            with: [
                'user' => $this->user,
                'paymentSchedule' => $this->paymentSchedule,
            ],
        );
    }
}
