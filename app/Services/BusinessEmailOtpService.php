<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

class BusinessEmailOtpService
{
    /**
     * Generate and store OTP for business email verification.
     */
    public function generateOtp(string $email, int $businessId): string
    {
        // Rate limit: 5 requests per email per 15 minutes
        $key = 'business_email_otp_generate:'.md5(strtolower($email).':'.$businessId);
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
        $cacheKey = $this->getOtpCacheKey($email, $businessId);
        Cache::put($cacheKey, $otp, now()->addMinutes(10));

        return $otp;
    }

    /**
     * Verify OTP for business email.
     */
    public function verifyOtp(string $email, string $otp, int $businessId): bool
    {
        // Rate limit: 10 attempts per email per 15 minutes
        $key = 'business_email_otp_verify:'.md5(strtolower($email).':'.$businessId);
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

        $cacheKey = $this->getOtpCacheKey($email, $businessId);
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
    public function hasOtp(string $email, int $businessId): bool
    {
        $cacheKey = $this->getOtpCacheKey($email, $businessId);

        return Cache::has($cacheKey);
    }

    /**
     * Get cache key for OTP.
     */
    private function getOtpCacheKey(string $email, int $businessId): string
    {
        return 'business_email_otp:'.md5(strtolower($email).':'.$businessId);
    }
}
