<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EmailService
{
    /**
     * Security-critical email types that cannot be opted out of.
     */
    private const MANDATORY_EMAILS = [
        'login_notification',
        'password_changed',
        'two_factor_enabled',
        'two_factor_disabled',
    ];

    /**
     * Send an email to a user if they haven't opted out.
     */
    public function send(User $user, Mailable $mailable, string $emailType): bool
    {
        try {
            // Check if user has an email address
            if (! $user->email) {
                Log::warning("Cannot send email {$emailType} to user {$user->id}: user has no email address");

                return false;
            }

            // Security emails are always sent, cannot be opted out
            $isMandatory = in_array($emailType, self::MANDATORY_EMAILS);

            // Check if user should receive this email type (skip for mandatory security emails)
            if (! $isMandatory && ! $user->shouldReceiveEmail($emailType)) {
                Log::info("User {$user->id} has opted out of {$emailType} emails");

                return false;
            }

            // Queue the email (after commit to ensure user is saved - configured in queue.php)
            Mail::to($user->email)->queue($mailable);

            Log::info("Email queued: {$emailType} to user {$user->id}".($isMandatory ? ' (mandatory security email)' : ''));

            return true;
        } catch (\Exception $e) {
            Log::error("Failed to send email {$emailType} to user {$user->id}: ".$e->getMessage(), [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return false;
        }
    }

    /**
     * Send email to multiple users.
     */
    public function sendToMany(array $users, Mailable $mailable, string $emailType): int
    {
        $sent = 0;
        foreach ($users as $user) {
            if ($this->send($user, $mailable, $emailType)) {
                $sent++;
            }
        }

        return $sent;
    }
}
