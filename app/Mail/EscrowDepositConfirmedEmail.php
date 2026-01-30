<?php

namespace App\Mail;

use App\Models\Business;
use App\Models\EscrowDeposit;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EscrowDepositConfirmedEmail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public User $user,
        public Business $business,
        public EscrowDeposit $deposit
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Escrow Deposit Confirmed: '.number_format($this->deposit->authorized_amount, 2).' '.$this->deposit->currency.' - '.$this->business->name,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.escrow-deposit-confirmed',
            with: [
                'user' => $this->user,
                'businessData' => $this->business, // For content display only
                'business' => null, // Explicitly null for email branding (app-related email from SwiftPay)
                'deposit' => $this->deposit,
            ],
        );
    }
}
