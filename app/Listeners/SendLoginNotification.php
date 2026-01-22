<?php

namespace App\Listeners;

use App\Mail\LoginNotificationEmail;
use App\Services\EmailService;
use Illuminate\Auth\Events\Login;

class SendLoginNotification
{
    /**
     * Handle the event.
     *
     * This listener runs synchronously to have access to request() context.
     * The email itself is still queued via EmailService for performance.
     */
    public function handle(Login $event): void
    {
        $user = $event->user;
        $request = request();

        $emailService = app(EmailService::class);
        $emailService->send(
            $user,
            new LoginNotificationEmail(
                $user,
                $request->ip() ?? 'Unknown',
                $request->userAgent() ?? 'Unknown'
            ),
            'login_notification'
        );
    }
}
