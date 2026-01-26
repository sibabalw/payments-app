<?php

namespace App\Http\Responses;

use App\Mail\AdminOtpEmail;
use App\Services\AdminOtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse as TwoFactorLoginResponseContract;
use Laravel\Fortify\Fortify;
use Symfony\Component\HttpFoundation\Response;

class TwoFactorLoginResponse implements TwoFactorLoginResponseContract
{
    /**
     * After 2FA code is valid, admins must also pass email OTP (same as password-only flow).
     */
    public function toResponse($request): Response
    {
        $user = $request->user();

        if ($user->is_admin) {
            $payload = [
                'user_id' => $user->id,
                'email' => $user->email,
                'expires_at' => now()->addMinutes(10)->timestamp,
            ];
            $request->session()->put('admin_otp_pending', Crypt::encryptString(json_encode($payload)));

            $otpService = app(AdminOtpService::class);
            $otp = $otpService->generateOtp($user->id, $user->email);
            Mail::to($user->email)->queue(new AdminOtpEmail($user->email, $user->name, $otp));

            Auth::logout();

            return $request->wantsJson()
                ? new JsonResponse(['two_factor' => false, 'redirect' => route('admin.otp.show')], 200)
                : redirect()->route('admin.otp.show')->with('status', 'We sent a verification code to your email.');
        }

        return $request->wantsJson()
            ? new JsonResponse('', 204)
            : redirect()->intended(Fortify::redirects('login'));
    }
}
