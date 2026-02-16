<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class LoginNotificationEmail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The ID of the user to send notification for.
     */
    protected int $userId;

    /**
     * Create a new message instance.
     */
    public function __construct(
        User $user,
        public string $ipAddress,
        public string $userAgent,
        public ?array $location = null
    ) {
        // Store only the ID - reload fresh to avoid serialization issues
        $this->userId = $user->id;
    }

    /**
     * Get the user, always loading it fresh.
     * Throws an exception if the user doesn't exist to prevent sending invalid emails.
     */
    protected function getUser(): User
    {
        $user = User::query()
            ->where('id', $this->userId)
            ->first();

        if (! $user) {
            Log::warning('User not found when sending login notification email - failing job silently', [
                'user_id' => $this->userId,
            ]);

            // Throw a ModelNotFoundException to fail the job without retrying
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException(
                "User with ID {$this->userId} not found"
            );
        }

        return $user;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'New Login to Your SwiftPay Account',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $user = $this->getUser();

        return new Content(
            view: 'emails.login-notification',
            with: [
                'user' => $user,
                'ipAddress' => $this->ipAddress,
                'userAgent' => $this->userAgent,
                'location' => $this->location,
            ],
        );
    }
}
