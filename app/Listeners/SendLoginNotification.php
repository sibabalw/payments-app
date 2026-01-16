<?php

namespace App\Listeners;

use App\Mail\LoginNotificationEmail;
use App\Services\EmailService;
use Illuminate\Auth\Events\Login;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendLoginNotification implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
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
