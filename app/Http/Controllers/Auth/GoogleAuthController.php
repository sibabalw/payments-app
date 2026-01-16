<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\WelcomeEmail;
use App\Models\User;
use App\Services\EmailService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{
    /**
     * Redirect the user to the Google authentication page.
     */
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('google')->redirect();
    }

    /**
     * Obtain the user information from Google.
     */
    public function callback(): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();

            $user = User::where('email', $googleUser->getEmail())->first();

            $isNewUser = false;
            
            if ($user) {
                // Update existing user with Google info and verify email if not already verified
                $user->update([
                    'google_id' => $googleUser->getId(),
                    'avatar' => $googleUser->getAvatar(),
                    'email_verified_at' => $user->email_verified_at ?? now(), // Verify if not already verified
                ]);
            } else {
                // Create new user
                $isNewUser = true;
                $user = User::create([
                    'name' => $googleUser->getName(),
                    'email' => $googleUser->getEmail(),
                    'google_id' => $googleUser->getId(),
                    'avatar' => $googleUser->getAvatar(),
                    'email_verified_at' => now(),
                    'password' => bcrypt(str()->random(32)), // Random password since OAuth
                ]);
                
                // Send welcome email for new users
                $emailService = app(EmailService::class);
                $emailService->send($user, new WelcomeEmail($user), 'welcome');
            }

            // Refresh user to ensure we have the latest data
            $user->refresh();

            // Auto-select business if user has businesses but no current_business_id
            if (!$user->current_business_id) {
                $firstBusiness = $user->ownedBusinesses()->first() ?? $user->businesses()->first();
                if ($firstBusiness) {
                    $user->update(['current_business_id' => $firstBusiness->id]);
                    $user->refresh();
                }
            }

            Auth::login($user, true);

            // If user has already completed onboarding, go to dashboard
            if ($user->onboarding_completed_at) {
                return redirect('/dashboard');
            }

            // Check if user has businesses, if so mark onboarding as completed
            $hasBusinesses = $user->businesses()->count() > 0 || $user->ownedBusinesses()->count() > 0;
            
            if ($hasBusinesses) {
                $user->update(['onboarding_completed_at' => now()]);
                return redirect('/dashboard');
            }

            return redirect('/onboarding');
        } catch (\Exception $e) {
            return redirect()->route('login')
                ->with('error', 'Failed to authenticate with Google. Please try again.');
        }
    }
}
