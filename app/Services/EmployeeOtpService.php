<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

class EmployeeOtpService
{
    /**
     * Generate and store OTP for employee email.
     */
    public function generateOtp(string $email): string
    {
        // Rate limit: 5 requests per email per 15 minutes (more reasonable)
        $key = 'employee_otp_generate:'.md5(strtolower($email));
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
        $cacheKey = $this->getOtpCacheKey($email);
        Cache::put($cacheKey, $otp, now()->addMinutes(10));

        return $otp;
    }

    /**
     * Verify OTP for employee email.
     */
    public function verifyOtp(string $email, string $otp): bool
    {
        // Rate limit: 10 attempts per email per 15 minutes (more reasonable)
        $key = 'employee_otp_verify:'.md5(strtolower($email));
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

        $cacheKey = $this->getOtpCacheKey($email);
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
    public function hasOtp(string $email): bool
    {
        $cacheKey = $this->getOtpCacheKey($email);

        return Cache::has($cacheKey);
    }

    /**
     * Get cache key for OTP.
     */
    private function getOtpCacheKey(string $email): string
    {
        return 'employee_otp:'.md5(strtolower($email));
    }
}
