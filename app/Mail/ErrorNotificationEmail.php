<?php

namespace App\Mail;

use App\Models\ErrorLog;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ErrorNotificationEmail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public ErrorLog $errorLog
    ) {
        $this->errorLog->loadMissing('user');
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $level = strtoupper($this->errorLog->level);
        $subject = "[{$level}] Application Error: {$this->errorLog->message}";

        // Truncate subject if too long
        if (strlen($subject) > 100) {
            $subject = substr($subject, 0, 97).'...';
        }

        return new Envelope(
            from: new Address(config('mail.from.address'), config('mail.from.name')),
            subject: $subject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.error-notification',
            with: [
                'errorLog' => $this->errorLog,
            ],
        );
    }
}
