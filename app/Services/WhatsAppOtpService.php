<?php

namespace App\Services;

use App\Mail\WhatsAppOtpEmail;
use App\Models\Business;
use App\Models\User;
use App\Models\WhatsAppSession;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

class WhatsAppOtpService
{
    /**
     * Generate and send OTP for WhatsApp login.
     */
    public function generateAndSendOtp(string $phoneNumber, string $email): void
    {
        // Rate limit: 5 OTP requests per phone per 15 minutes
        $key = 'whatsapp_otp_generate:'.md5($phoneNumber);
        $executed = RateLimiter::attempt(
            $key,
            5,
            function () {
                // Rate limit passed
            },
            900 // 15 minutes
        );

        if (! $executed) {
            throw new \Exception('Too many OTP requests. Please wait before requesting another code.');
        }

        // Generate 6-digit OTP
        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // Store OTP in cache with phone number and email
        $cacheKey = $this->getOtpCacheKey($phoneNumber);
        Cache::put($cacheKey, [
            'otp' => $otp,
            'email' => strtolower($email),
        ], now()->addMinutes(10));

        // Send OTP via email
        $user = User::where('email', strtolower($email))->first();
        if ($user) {
            Mail::to($user->email)->queue(new WhatsAppOtpEmail($user, $otp, $phoneNumber));
        }
    }

    /**
     * Verify OTP and create session if valid.
     */
    public function verifyOtp(string $phoneNumber, string $otp): bool
    {
        // Rate limit: 10 verification attempts per phone per 15 minutes
        $key = 'whatsapp_otp_verify:'.md5($phoneNumber);
        $executed = RateLimiter::attempt(
            $key,
            10,
            function () {
                // Rate limit passed
            },
            900 // 15 minutes
        );

        if (! $executed) {
            return false;
        }

        $cacheKey = $this->getOtpCacheKey($phoneNumber);
        $stored = Cache::get($cacheKey);

        if (! $stored || ! isset($stored['otp']) || ! isset($stored['email'])) {
            return false;
        }

        // Verify OTP
        if ($stored['otp'] !== $otp) {
            return false;
        }

        // Get user and their default business
        $user = User::where('email', $stored['email'])->first();
        if (! $user) {
            return false;
        }

        // Get user's current business or first available business
        $business = $user->currentBusiness ?? $user->ownedBusinesses()->first() ?? $user->businesses()->first();
        if (! $business) {
            return false;
        }

        // Create or update WhatsApp session
        $session = WhatsAppSession::updateOrCreate(
            [
                'phone_number' => $phoneNumber,
                'business_id' => $business->id,
            ],
            [
                'user_id' => $user->id,
                'verified_at' => now(),
                'expires_at' => now()->addHours(24),
            ]
        );

        // Clear OTP from cache (one-time use)
        Cache::forget($cacheKey);

        return true;
    }

    /**
     * Check if OTP exists for phone number.
     */
    public function hasOtp(string $phoneNumber): bool
    {
        $cacheKey = $this->getOtpCacheKey($phoneNumber);

        return Cache::has($cacheKey);
    }

    /**
     * Get the email associated with a pending OTP.
     */
    public function getPendingEmail(string $phoneNumber): ?string
    {
        $cacheKey = $this->getOtpCacheKey($phoneNumber);
        $stored = Cache::get($cacheKey);

        return $stored['email'] ?? null;
    }

    /**
     * Get cache key for OTP storage.
     */
    protected function getOtpCacheKey(string $phoneNumber): string
    {
        return 'whatsapp_otp:'.md5($phoneNumber);
    }
}
