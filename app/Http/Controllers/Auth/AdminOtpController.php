<?php

namespace App\Http\Controllers\Auth;

use App\Events\AdminLoginCompleted;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\VerifyAdminOtpRequest;
use App\Mail\AdminOtpEmail;
use App\Models\User;
use App\Services\AdminOtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

class AdminOtpController extends Controller
{
    /**
     * Show the admin OTP challenge page (only valid with pending session).
     */
    public function show(Request $request): Response|RedirectResponse
    {
        $payload = $this->getPendingPayload($request);

        if ($payload === null) {
            return redirect()->route('login')
                ->with('error', 'Your verification session expired or is invalid. Please sign in again.');
        }

        $maskedEmail = $this->maskEmail($payload['email']);

        return Inertia::render('admin/otp-challenge', [
            'email' => $maskedEmail,
            'status' => $request->session()->get('status'),
            'error' => $request->session()->get('error'),
        ]);
    }

    /**
     * Verify the OTP and complete admin login.
     */
    public function verify(VerifyAdminOtpRequest $request, AdminOtpService $otpService): RedirectResponse
    {
        $payload = $this->getPendingPayload($request);

        if ($payload === null) {
            return redirect()->route('login')
                ->with('error', 'Your verification session expired or is invalid. Please sign in again.');
        }

        $userId = (int) $payload['user_id'];
        $email = $payload['email'];
        $otp = $request->validated()['otp'];

        if (! $otpService->verifyOtp($userId, $email, $otp)) {
            return redirect()->route('admin.otp.show')
                ->with('error', 'Invalid or expired verification code. Please try again or request a new code.');
        }

        $request->session()->forget('admin_otp_pending');

        $user = User::findOrFail($userId);
        Auth::login($user, true);

        event(new AdminLoginCompleted($user));

        return redirect()->intended(route('admin.dashboard'))
            ->with('status', 'Welcome back. You have signed in successfully.');
    }

    /**
     * Resend the OTP email.
     */
    public function resend(Request $request, AdminOtpService $otpService): RedirectResponse
    {
        $payload = $this->getPendingPayload($request);

        if ($payload === null) {
            return redirect()->route('login')
                ->with('error', 'Your verification session expired or is invalid. Please sign in again.');
        }

        $userId = (int) $payload['user_id'];
        $email = $payload['email'];
        $user = User::find($userId);

        if (! $user) {
            $request->session()->forget('admin_otp_pending');

            return redirect()->route('login')
                ->with('error', 'Your verification session is invalid. Please sign in again.');
        }

        try {
            $otp = $otpService->generateOtp($userId, $email);
            Mail::to($email)->queue(new AdminOtpEmail($user->email, $user->name, $otp));
        } catch (\Exception $e) {
            return redirect()->route('admin.otp.show')
                ->with('error', 'We could not send a new code. Please wait a moment and try again.');
        }

        return redirect()->route('admin.otp.show')
            ->with('status', 'A new verification code has been sent to your email.');
    }

    /**
     * Get and validate pending admin payload from session; return null if missing or expired.
     *
     * @return array{user_id: int, email: string, expires_at: int}|null
     */
    private function getPendingPayload(Request $request): ?array
    {
        $encrypted = $request->session()->get('admin_otp_pending');

        if (! $encrypted || ! is_string($encrypted)) {
            return null;
        }

        try {
            $json = Crypt::decryptString($encrypted);
            $payload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return null;
        }

        if (! is_array($payload)
            || ! isset($payload['user_id'], $payload['email'], $payload['expires_at'])
            || (int) $payload['expires_at'] < now()->timestamp
        ) {
            return null;
        }

        return [
            'user_id' => (int) $payload['user_id'],
            'email' => (string) $payload['email'],
            'expires_at' => (int) $payload['expires_at'],
        ];
    }

    private function maskEmail(string $email): string
    {
        $parts = explode('@', $email, 2);
        if (count($parts) !== 2) {
            return '***';
        }
        $local = $parts[0];
        $domain = $parts[1];
        $len = strlen($local);
        if ($len <= 2) {
            $masked = str_repeat('*', $len);
        } else {
            $masked = substr($local, 0, 2).str_repeat('*', min($len - 2, 6)).($len > 8 ? substr($local, -1) : '');
        }

        return $masked.'@'.$domain;
    }
}
