<?php

namespace App\Mail;

use App\Models\Business;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EscrowBalanceLowEmail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public User $user,
        public Business $business,
        public float $currentBalance,
        public float $requiredAmount
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        // Get business email and name, fallback to SwiftPay defaults
        $fromEmail = $this->business->email ?? config('mail.from.address');
        $fromName = $this->business->name ?? config('mail.from.name');

        return new Envelope(
            from: new Address($fromEmail, $fromName),
            subject: 'Low Escrow Balance Alert: '.$this->business->name,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.escrow-balance-low',
            with: [
                'user' => $this->user,
                'business' => $this->business,
                'currentBalance' => $this->currentBalance,
                'requiredAmount' => $this->requiredAmount,
            ],
        );
    }
}
