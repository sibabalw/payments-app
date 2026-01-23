<?php

namespace App\Listeners;

use App\Mail\LoginNotificationEmail;
use App\Services\EmailService;
use App\Services\GeolocationService;
use Illuminate\Auth\Events\Login;

class SendLoginNotification
{
    public function __construct(
        private GeolocationService $geolocationService
    ) {}

    /**
     * Handle the event.
     *
     * This listener runs synchronously to have access to request() context.
     * The email itself is still queued via EmailService for performance.
     */
    public function handle(Login $event): void
    {
        $user = $event->user;
        $request = request();
        $ipAddress = $this->getClientIpAddress($request);

        // Get location from IP (with multiple fallbacks)
        $location = null;
        if ($ipAddress !== 'Unknown') {
            try {
                $location = $this->geolocationService->getLocationFromIp($ipAddress, $request);
            } catch (\Exception $e) {
                // Silently fail - don't break login flow if geolocation fails
            }
        }
        
        // Ensure we always have a location, especially for localhost/private IPs
        if ($location === null || !is_array($location)) {
            if ($ipAddress === '127.0.0.1' || $ipAddress === '::1') {
                $location = [
                    'city' => 'Local',
                    'region' => 'Private',
                    'country' => 'Network',
                    'source' => 'fallback',
                ];
            } elseif ($ipAddress !== 'Unknown' && (str_starts_with($ipAddress, '192.168.') || str_starts_with($ipAddress, '10.') || str_starts_with($ipAddress, '172.'))) {
                $location = [
                    'city' => 'Local',
                    'region' => 'Private',
                    'country' => 'Network',
                    'source' => 'fallback',
                ];
            } else {
                // Default fallback for any other case
                $location = [
                    'city' => null,
                    'region' => null,
                    'country' => 'Unknown',
                    'source' => 'fallback',
                ];
            }
        }

        $emailService = app(EmailService::class);
        $emailService->send(
            $user,
            new LoginNotificationEmail(
                $user,
                $ipAddress,
                $request->userAgent() ?? 'Unknown',
                $location
            ),
            'login_notification'
        );
    }

    /**
     * Get the real client IP address, checking proxy headers.
     */
    private function getClientIpAddress($request): string
    {
        // Check X-Forwarded-For header (most common proxy header)
        $forwardedFor = $request->header('X-Forwarded-For');
        if ($forwardedFor) {
            // X-Forwarded-For can contain multiple IPs, get the first one
            $ips = explode(',', $forwardedFor);
            $ip = trim($ips[0]);
            if ($ip && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }

        // Check X-Real-IP header (Nginx proxy)
        $realIp = $request->header('X-Real-IP');
        if ($realIp && filter_var($realIp, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return $realIp;
        }

        // Check CF-Connecting-IP (Cloudflare)
        $cfIp = $request->header('CF-Connecting-IP');
        if ($cfIp && filter_var($cfIp, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return $cfIp;
        }

        // Fallback to Laravel's ip() method
        $ip = $request->ip();
        
        // If it's localhost/private IP and we have X-Forwarded-For, use the first public IP from it
        if (($ip === '127.0.0.1' || $ip === '::1' || $this->isPrivateIp($ip)) && $forwardedFor) {
            $ips = explode(',', $forwardedFor);
            foreach ($ips as $candidateIp) {
                $candidateIp = trim($candidateIp);
                if ($candidateIp && filter_var($candidateIp, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $candidateIp;
                }
            }
        }

        return $ip ?? 'Unknown';
    }

    /**
     * Check if an IP address is private/local.
     */
    private function isPrivateIp(string $ip): bool
    {
        if ($ip === '127.0.0.1' || $ip === '::1') {
            return true;
        }

        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    }
}
