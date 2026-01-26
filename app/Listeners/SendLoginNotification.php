<?php

namespace App\Listeners;

use App\Events\AdminLoginCompleted;
use App\Mail\LoginNotificationEmail;
use App\Models\User;
use App\Services\EmailService;
use App\Services\GeolocationService;
use Illuminate\Auth\Events\Login;

class SendLoginNotification
{
    public function __construct(
        private GeolocationService $geolocationService
    ) {}

    /**
     * Handle Login or AdminLoginCompleted. Same controller that logs in dispatches
     * AdminLoginCompleted for admins (after OTP or Google), so no flags needed.
     */
    public function handle(Login|AdminLoginCompleted $event): void
    {
        $user = $event->user;

        if ($event instanceof Login && $user->is_admin) {
            return;
        }

        $this->sendNotification($user);
    }

    private function sendNotification(User $user): void
    {
        $request = request();
        $ipAddress = $this->getClientIpAddress($request);

        // When request is from localhost, try to show the machine's local network IP in the email
        $displayIp = $ipAddress;
        if ($ipAddress === '127.0.0.1' || $ipAddress === '::1') {
            $localNetworkIp = $this->getLocalNetworkIp();
            if ($localNetworkIp !== null) {
                $displayIp = $localNetworkIp;
            }
        }

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
        if ($location === null || ! is_array($location)) {
            if ($ipAddress === '127.0.0.1' || $ipAddress === '::1') {
                $location = [
                    'city' => 'Local network',
                    'region' => '',
                    'country' => '',
                    'source' => 'fallback',
                ];
            } elseif ($ipAddress !== 'Unknown' && (str_starts_with($ipAddress, '192.168.') || str_starts_with($ipAddress, '10.') || str_starts_with($ipAddress, '172.'))) {
                $location = [
                    'city' => 'Local network',
                    'region' => '',
                    'country' => '',
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

        app(EmailService::class)->send(
            $user,
            new LoginNotificationEmail(
                $user,
                $displayIp,
                $request->userAgent() ?? 'Unknown',
                $location
            ),
            'login_notification'
        );
    }

    /**
     * Get this machine's primary local network IPv4 address (e.g. 192.168.x.x).
     * Used when the client IP is localhost so the email shows a recognizable network address.
     */
    private function getLocalNetworkIp(): ?string
    {
        if (! function_exists('net_get_interfaces')) {
            return null;
        }

        $interfaces = @net_get_interfaces();
        if (! is_array($interfaces)) {
            return null;
        }

        foreach ($interfaces as $interface) {
            $unicast = $interface['unicast'] ?? [];
            if (! is_array($unicast)) {
                continue;
            }
            foreach ($unicast as $addressInfo) {
                $address = $addressInfo['address'] ?? null;
                if ($address === null) {
                    continue;
                }
                // Skip loopback and IPv6
                if ($address === '127.0.0.1' || str_starts_with($address, '::')) {
                    continue;
                }
                if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                    return $address;
                }
            }
        }

        return null;
    }

    /**
     * Get the real client IP address, checking proxy headers.
     * When behind Docker/reverse proxy, the connecting IP (e.g. 172.18.0.1) is not the user's
     * public IP; we need X-Forwarded-For, X-Real-IP, Forwarded, or similar to get it.
     */
    private function getClientIpAddress($request): string
    {
        $candidates = $this->getClientIpCandidates($request);

        foreach ($candidates as $ip) {
            if ($ip && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }

        $ip = $request->ip();

        return $ip ?? 'Unknown';
    }

    /**
     * Collect possible client IPs from all proxy headers, in order of preference (client-first).
     */
    private function getClientIpCandidates($request): array
    {
        $candidates = [];

        $forwardedFor = $request->header('X-Forwarded-For');
        if ($forwardedFor) {
            $ips = array_map('trim', explode(',', $forwardedFor));
            $candidates = array_merge($candidates, $ips);
        }

        $realIp = $request->header('X-Real-IP');
        if ($realIp) {
            $candidates[] = trim($realIp);
        }

        $cfIp = $request->header('CF-Connecting-IP');
        if ($cfIp) {
            $candidates[] = trim($cfIp);
        }

        $trueClient = $request->header('True-Client-IP');
        if ($trueClient) {
            $candidates[] = trim($trueClient);
        }

        $forwarded = $request->header('Forwarded');
        if ($forwarded && preg_match('/\bfor="?([^";]+)"?/i', $forwarded, $m)) {
            $candidates[] = trim($m[1]);
        }

        return array_values(array_unique(array_filter($candidates)));
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
