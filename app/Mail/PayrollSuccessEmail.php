<?php

namespace App\Mail;

use App\Models\PayrollJob;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PayrollSuccessEmail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public User $user,
        public PayrollJob $payrollJob
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Payroll Payment Successful: '.number_format($this->payrollJob->net_salary, 2).' '.$this->payrollJob->currency,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.payroll-success',
            with: [
                'user' => $this->user,
                'payrollJob' => $this->payrollJob,
            ],
        );
    }
}
