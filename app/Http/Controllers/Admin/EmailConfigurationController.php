<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

class EmailConfigurationController extends Controller
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * Display email configuration page.
     */
    public function index(): Response
    {
        $mailConfig = [
            'default' => config('mail.default'),
            'mailer' => config('mail.mailers.'.config('mail.default').'.transport'),
            'host' => config('mail.mailers.'.config('mail.default').'.host'),
            'port' => config('mail.mailers.'.config('mail.default').'.port'),
            'from_address' => config('mail.from.address'),
            'from_name' => config('mail.from.name'),
        ];

        return Inertia::render('admin/email-configuration/index', [
            'mailConfig' => $mailConfig,
        ]);
    }

    /**
     * Test email configuration.
     */
    public function test(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        try {
            Mail::raw('This is a test email from the admin panel.', function ($message) use ($validated) {
                $message->to($validated['email'])
                    ->subject('Test Email - '.config('app.name'));
            });

            $this->auditService->log(
                'email.test_sent',
                'Admin sent test email',
                null,
                ['test_email' => $validated['email']]
            );

            return back()->with('success', 'Test email sent successfully to '.$validated['email']);
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to send test email: '.$e->getMessage());
        }
    }
}
