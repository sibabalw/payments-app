<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Mail\UserEmailOtpEmail;
use App\Services\UserEmailOtpService;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected UserEmailOtpService $otpService
    ) {}

    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        $user = $request->user();

        // Check if there's a pending email change
        $pendingEmail = session('user_email_change_'.$user->id);
        $otpSent = session('user_email_otp_sent_'.$user->id, false);
        $otpExpiresAt = session('user_email_otp_expires_'.$user->id);

        // If OTP has expired, clear the session data
        if ($otpSent && $otpExpiresAt && now()->isAfter($otpExpiresAt)) {
            session()->forget([
                'user_email_change_'.$user->id,
                'user_email_otp_sent_'.$user->id,
                'user_email_otp_expires_'.$user->id,
                'user_email_verified_'.$user->id,
            ]);
            $pendingEmail = null;
            $otpSent = false;
        }

        $props = [
            'mustVerifyEmail' => $user instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
            'pendingEmail' => $pendingEmail,
            'otpSent' => $otpSent,
        ];

        if ($request->routeIs('admin.account.*')) {
            return Inertia::render('admin/account/profile', $props);
        }

        return Inertia::render('settings/profile', $props);
    }

    /**
     * Send OTP for email verification.
     */
    public function sendEmailOtp(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => 'required|email|max:255|unique:users,email,'.$request->user()->id,
        ]);

        $user = $request->user();
        $newEmail = strtolower($validated['email']);

        // Check if email is actually different
        if ($newEmail === strtolower($user->email)) {
            return back()->withErrors(['email' => 'This is already your current email address.']);
        }

        try {
            // Generate OTP
            $otp = $this->otpService->generateOtp($newEmail, $user->id);

            // Send OTP email
            Mail::to($newEmail)->queue(new UserEmailOtpEmail($user, $newEmail, $otp));

            // Store pending email in session with expiration time
            session([
                'user_email_change_'.$user->id => $newEmail,
                'user_email_otp_sent_'.$user->id => true,
                'user_email_otp_expires_'.$user->id => now()->addMinutes(10),
            ]);

            return back()->with('status', 'OTP code has been sent to your new email address. Please verify to complete the email change.');
        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), 'Too many') || str_contains($e->getMessage(), 'rate limit')) {
                return back()->withErrors([
                    'email' => 'Too many OTP requests. Please wait a few minutes before requesting another code.',
                ])->withInput();
            }
            throw $e;
        }
    }

    /**
     * Verify OTP for email change.
     */
    public function verifyEmailOtp(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => 'required|email|max:255',
            'otp' => 'required|string|size:6|regex:/^[0-9]{6}$/',
        ]);

        $user = $request->user();
        $newEmail = strtolower($validated['email']);
        $otp = $validated['otp'];

        // Verify OTP
        if (! $this->otpService->verifyOtp($newEmail, $otp, $user->id)) {
            return back()->withErrors([
                'otp' => 'Invalid or expired OTP code. Please request a new one.',
            ])->withInput();
        }

        // Verify the email matches the pending email in session
        $pendingEmail = session('user_email_change_'.$user->id);
        if ($pendingEmail !== $newEmail) {
            return back()->withErrors([
                'email' => 'Email does not match the pending email change. Please request a new OTP.',
            ])->withInput();
        }

        // Immediately update the user email
        $oldEmail = $user->email;
        $user->email = $newEmail;
        $user->email_verified_at = null; // Reset verification status
        $user->save();

        // Clear all email change related session data
        session()->forget([
            'user_email_change_'.$user->id,
            'user_email_otp_sent_'.$user->id,
            'user_email_otp_expires_'.$user->id,
            'user_email_verified_'.$user->id,
        ]);

        return back()->with('status', 'Email updated successfully to '.$newEmail);
    }

    /**
     * Cancel pending email OTP verification.
     */
    public function cancelEmailOtp(Request $request): RedirectResponse
    {
        $user = $request->user();

        // Clear all email change related session data
        session()->forget([
            'user_email_change_'.$user->id,
            'user_email_otp_sent_'.$user->id,
            'user_email_otp_expires_'.$user->id,
            'user_email_verified_'.$user->id,
        ]);

        return back()->with('status', 'Email change cancelled. You can enter a new email address.');
    }

    /**
     * Update the user's profile settings.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $user->fill($request->validated());

        $newEmail = strtolower($user->email);
        $oldEmail = strtolower($user->getOriginal('email') ?? $user->email);

        // If email is changing, require OTP verification
        if ($newEmail !== $oldEmail) {
            $pendingEmail = session('user_email_change_'.$user->id);
            $emailVerified = session('user_email_verified_'.$user->id, false);

            if ($pendingEmail !== $newEmail || ! $emailVerified) {
                return back()->withErrors([
                    'email' => 'Please verify your new email address with OTP before saving changes.',
                ])->withInput();
            }

            // Clear email verification session data
            session()->forget([
                'user_email_change_'.$user->id,
                'user_email_otp_sent_'.$user->id,
                'user_email_verified_'.$user->id,
            ]);

            // Reset email verification status when email changes
            $user->email_verified_at = null;
        }

        // Wrap user update in transaction for future-proofing
        DB::transaction(function () use ($user) {
            $user->save();
        });

        $redirectRoute = $request->routeIs('admin.account.*')
            ? 'admin.account.profile.edit'
            : 'profile.edit';

        return to_route($redirectRoute)->with('status', 'Profile updated successfully.');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
