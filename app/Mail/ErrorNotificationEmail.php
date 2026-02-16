<?php

namespace App\Mail;

use App\Models\ErrorLog;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ErrorNotificationEmail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The ID of the error log to send notification for.
     */
    protected int $errorLogId;

    /**
     * Create a new message instance.
     */
    public function __construct(
        ErrorLog $errorLog
    ) {
        // Store only the ID - reload fresh to avoid serialization issues
        $this->errorLogId = $errorLog->id;
    }

    /**
     * Get the error log, always loading it fresh.
     * Throws an exception if the error log doesn't exist to prevent sending invalid emails.
     */
    protected function getErrorLog(): ErrorLog
    {
        $errorLog = ErrorLog::query()
            ->where('id', $this->errorLogId)
            ->first();

        if (! $errorLog) {
            Log::warning('Error log not found when sending notification email - failing job silently', [
                'error_log_id' => $this->errorLogId,
            ]);

            // Throw a ModelNotFoundException to fail the job without retrying
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException(
                "Error log with ID {$this->errorLogId} not found"
            );
        }

        $errorLog->loadMissing('user');

        return $errorLog;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $errorLog = $this->getErrorLog();

        $level = strtoupper($errorLog->level);
        $subject = "[{$level}] Application Error: {$errorLog->message}";

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
        $errorLog = $this->getErrorLog();

        return new Content(
            view: 'emails.error-notification',
            with: [
                'errorLog' => $errorLog,
            ],
        );
    }
}
