<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\WelcomeEmail;
use App\Services\EmailService;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;

class EmailVerificationController extends Controller
{
    /**
     * Mark the user's email address as verified.
     */
    public function __invoke(EmailVerificationRequest $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->intended('/dashboard')->with('verified', true);
        }

        $user = $request->user();

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
            
            // Send welcome email after verification
            $emailService = app(EmailService::class);
            $emailService->send($user, new WelcomeEmail($user), 'welcome');
        }

        // Auto-select business if user has businesses but no current_business_id
        if (!$user->current_business_id) {
            $firstBusiness = $user->ownedBusinesses()->first() ?? $user->businesses()->first();
            if ($firstBusiness) {
                $user->update(['current_business_id' => $firstBusiness->id]);
                $user->refresh();
            }
        }

        // If user has already completed onboarding, go to dashboard
        if ($user->onboarding_completed_at) {
            return redirect('/dashboard')->with('verified', true);
        }

        $hasBusinesses = $user->businesses()->count() > 0 || $user->ownedBusinesses()->count() > 0;

        if ($hasBusinesses) {
            $user->update(['onboarding_completed_at' => now()]);
            return redirect('/dashboard')->with('verified', true);
        }

        return redirect('/onboarding')->with('verified', true);
    }
}
