<?php

namespace App\Mail;

use App\Models\Business;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BusinessStatusChangedEmail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public User $user,
        public Business $business,
        public string $oldStatus,
        public string $newStatus,
        public ?string $reason = null
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $statusLabels = [
            'active' => 'Activated',
            'suspended' => 'Suspended',
            'banned' => 'Banned',
        ];

        return new Envelope(
            subject: 'Business Status Update: '.$this->business->name.' - '.($statusLabels[$this->newStatus] ?? ucfirst($this->newStatus)),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.business-status-changed',
            with: [
                'user' => $this->user,
                'businessData' => $this->business, // For content display only
                'business' => null, // Explicitly null for email branding (app-related email from SwiftPay)
                'oldStatus' => $this->oldStatus,
                'newStatus' => $this->newStatus,
                'reason' => $this->reason,
            ],
        );
    }
}
