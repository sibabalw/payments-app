<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

class AdminOtpService
{
    private const OTP_TTL_MINUTES = 10;

    private const RATE_LIMIT_DECAY_SECONDS = 600; // 10 minutes

    private const OTP_PURPOSE_LOGIN = 'login';

    /**
     * Generate and store OTP for admin email verification.
     * Stores HMAC-SHA256 hash of OTP; single-use, deleted on success.
     */
    public function generateOtp(int $userId, string $email): string
    {
        if (app()->environment('production') && config('cache.default') === 'array') {
            throw new \RuntimeException('Admin OTP requires a persistent cache (e.g. Redis) in production. Set CACHE_STORE=redis.');
        }

        $key = 'admin_otp_generate:'.md5(strtolower($email).':'.$userId);
        $executed = RateLimiter::attempt(
            $key,
            10,
            function () {
                //
            },
            self::RATE_LIMIT_DECAY_SECONDS
        );

        if (! $executed) {
            throw new \Exception('Too many OTP requests. Please wait before requesting another code.');
        }

        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $cacheKey = $this->getOtpCacheKey($userId, $email);
        $hash = $this->hashOtp($otp);
        Cache::put($cacheKey, $hash, now()->addMinutes(self::OTP_TTL_MINUTES));

        return $otp;
    }

    /**
     * Verify OTP for admin (constant-time comparison). Single-use: key is deleted on success.
     */
    public function verifyOtp(int $userId, string $email, string $otp): bool
    {
        $key = 'admin_otp_verify:'.md5(strtolower($email).':'.$userId);
        $executed = RateLimiter::attempt(
            $key,
            10,
            function () {
                //
            },
            self::RATE_LIMIT_DECAY_SECONDS
        );

        if (! $executed) {
            return false;
        }

        $cacheKey = $this->getOtpCacheKey($userId, $email);
        $storedHash = Cache::get($cacheKey);

        if ($storedHash === null || $storedHash === '') {
            return false;
        }

        if (! hash_equals($storedHash, $this->hashOtp($otp))) {
            return false;
        }

        Cache::forget($cacheKey);

        return true;
    }

    private function getOtpCacheKey(int $userId, string $email): string
    {
        return 'admin_otp:'.self::OTP_PURPOSE_LOGIN.':'.md5($userId.':'.strtolower($email));
    }

    private function hashOtp(string $otp): string
    {
        return hash_hmac('sha256', $otp, config('app.key'));
    }
}
