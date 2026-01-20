<?php

namespace App\Mail;

use App\Models\Employee;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EmployeeOtpEmail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public Employee $employee,
        public string $otp
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        // Load business relationship
        $this->employee->loadMissing('business');
        $business = $this->employee->business;

        // Get business email and name, fallback to Swift Pay defaults
        $fromEmail = $business->email ?? config('mail.from.address');
        $fromName = $business->name ?? config('mail.from.name');

        return new Envelope(
            from: new Address($fromEmail, $fromName),
            subject: 'Your Employee Sign-In Code',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        // Load business relationship
        $this->employee->loadMissing('business');
        $business = $this->employee->business;

        return new Content(
            view: 'emails.employee-otp',
            with: [
                'employee' => $this->employee,
                'business' => $business,
                'otp' => $this->otp,
                'user' => (object) ['email' => $this->employee->email], // For email layout compatibility
            ],
        );
    }
}
