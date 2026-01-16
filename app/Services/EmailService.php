<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class EmailService
{
    /**
     * Send an email to a user if they haven't opted out.
     */
    public function send(User $user, Mailable $mailable, string $emailType): bool
    {
        try {
            // Check if user should receive this email type
            if (!$user->shouldReceiveEmail($emailType)) {
                Log::info("User {$user->id} has opted out of {$emailType} emails");
                return false;
            }

            // Queue the email
            Mail::to($user->email)->queue($mailable);

            Log::info("Email queued: {$emailType} to user {$user->id}");

            return true;
        } catch (\Exception $e) {
            Log::error("Failed to send email {$emailType} to user {$user->id}: " . $e->getMessage());
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
