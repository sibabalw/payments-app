<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

class UserEmailOtpService
{
    /**
     * Generate and store OTP for user email verification.
     */
    public function generateOtp(string $email, int $userId): string
    {
        // Rate limit: 5 requests per email per 15 minutes
        $key = 'user_email_otp_generate:'.md5(strtolower($email).':'.$userId);
        $executed = RateLimiter::attempt(
            $key,
            $perMinute = 5,
            function () {
                // Rate limit passed
            },
            $decaySeconds = 900 // 15 minutes
        );

        if (! $executed) {
            throw new \Exception('Too many OTP requests. Please wait before requesting another code.');
        }

        // Generate 6-digit OTP
        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // Store OTP in cache with 10-minute expiration
        $cacheKey = $this->getOtpCacheKey($email, $userId);
        Cache::put($cacheKey, $otp, now()->addMinutes(10));

        return $otp;
    }

    /**
     * Verify OTP for user email.
     */
    public function verifyOtp(string $email, string $otp, int $userId): bool
    {
        // Rate limit: 10 attempts per email per 15 minutes
        $key = 'user_email_otp_verify:'.md5(strtolower($email).':'.$userId);
        $executed = RateLimiter::attempt(
            $key,
            $perMinute = 10,
            function () {
                // Rate limit passed
            },
            $decaySeconds = 900 // 15 minutes
        );

        if (! $executed) {
            return false;
        }

        $cacheKey = $this->getOtpCacheKey($email, $userId);
        $storedOtp = Cache::get($cacheKey);

        if (! $storedOtp) {
            return false;
        }

        // Case-insensitive comparison
        if (strcasecmp($storedOtp, $otp) !== 0) {
            return false;
        }

        // One-time use: delete OTP after verification
        Cache::forget($cacheKey);

        return true;
    }

    /**
     * Check if OTP exists for email (without consuming it).
     */
    public function hasOtp(string $email, int $userId): bool
    {
        $cacheKey = $this->getOtpCacheKey($email, $userId);

        return Cache::has($cacheKey);
    }

    /**
     * Get cache key for OTP.
     */
    private function getOtpCacheKey(string $email, int $userId): string
    {
        return 'user_email_otp:'.md5(strtolower($email).':'.$userId);
    }
}
