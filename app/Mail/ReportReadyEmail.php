<?php

namespace App\Mail;

use App\Models\ReportGeneration;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

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

        $fromEmail = $business?->email ?? config('mail.from.address');
        $fromName = $business?->name ?? config('mail.from.name');

        $reportTypeName = ucwords(str_replace('_', ' ', $this->reportGeneration->report_type));
        $formatName = strtoupper($this->reportGeneration->format);

        return new Envelope(
            from: new Address($fromEmail, $fromName),
            subject: "Your {$reportTypeName} Report ({$formatName})",
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
            ],
        );
    }

    /**
     * Get the attachments for the message.
     * Uses fromStorageDisk so the file is read when the queued mail runs.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        try {
            $reportGeneration = $this->reportGeneration->refresh();

            if (! $reportGeneration->file_path || ! $reportGeneration->filename) {
                return [];
            }

            if (! Storage::disk('local')->exists($reportGeneration->file_path)) {
                return [];
            }

            $mime = $reportGeneration->format === 'pdf'
                ? 'application/pdf'
                : 'text/csv; charset=UTF-8';

            return [
                Attachment::fromStorageDisk('local', $reportGeneration->file_path)
                    ->as($reportGeneration->filename)
                    ->withMime($mime),
            ];
        } catch (\Throwable $e) {
            report($e);

            return [];
        }
    }
}
