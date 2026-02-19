<?php

namespace App\Http\Responses;

use App\Mail\AdminOtpEmail;
use App\Services\AdminOtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Symfony\Component\HttpFoundation\Response;

class LoginResponse implements LoginResponseContract
{
    /**
     * Create an HTTP response that represents the object.
     *
     * Only used for password-based login (Fortify). Admin users must confirm
     * an email OTP before access. Google/OAuth admin logins do not use this
     * response and skip OTP (handled in GoogleAuthController).
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
            Mail::to($user->email)->send(new AdminOtpEmail($user->email, $user->name, $otp));

            Auth::logout();

            return $request->wantsJson()
                ? new JsonResponse(['two_factor' => false, 'redirect' => route('admin.otp.show')], 200)
                : redirect()->route('admin.otp.show')->with('status', 'We sent a verification code to your email.');
        }

        return $request->wantsJson()
            ? new JsonResponse(['two_factor' => false], 200)
            : redirect()->intended(config('fortify.home'));
    }
}
