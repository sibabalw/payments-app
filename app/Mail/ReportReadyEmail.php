<?php

namespace App\Mail;

use App\Models\ReportGeneration;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReportReadyEmail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public User $user,
        public ReportGeneration $reportGeneration
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $business = $this->reportGeneration->business;

        // Get business email and name, fallback to Swift Pay defaults
        $fromEmail = $business?->email ?? config('mail.from.address');
        $fromName = $business?->name ?? config('mail.from.name');

        $reportTypeName = ucwords(str_replace('_', ' ', $this->reportGeneration->report_type));
        $formatName = strtoupper($this->reportGeneration->format);

        return new Envelope(
            from: new Address($fromEmail, $fromName),
            subject: "Your {$reportTypeName} Report ({$formatName}) is Ready",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.report-ready',
            with: [
                'user' => $this->user,
                'reportGeneration' => $this->reportGeneration,
                'business' => $this->reportGeneration->business,
                'downloadUrl' => route('reports.download', $this->reportGeneration),
            ],
        );
    }
}
