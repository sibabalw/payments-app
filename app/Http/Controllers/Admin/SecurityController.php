<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Inertia;
use Inertia\Response;

class SecurityController extends Controller
{
    /**
     * Get database-agnostic date format expression.
     */
    private function dateFormat(string $column, string $format = '%Y-%m-%d'): string
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            // PostgreSQL: to_char(column, 'YYYY-MM-DD')
            $pgFormat = match ($format) {
                '%Y-%m-%d' => 'YYYY-MM-DD',
                '%Y-%m' => 'YYYY-MM',
                default => 'YYYY-MM-DD',
            };

            return "to_char({$column}, '{$pgFormat}')";
        } else {
            // MySQL/MariaDB: DATE_FORMAT(column, '%Y-%m-%d')
            return "DATE_FORMAT({$column}, '{$format}')";
        }
    }

    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * Display security management page.
     */
    public function index(): Response
    {
        // Rate limiting configuration
        $rateLimitConfig = $this->getRateLimitConfig();

        // Failed login attempts (last 24 hours)
        $failedLogins = AuditLog::query()
            ->where(function ($query) {
                $query->where('action', 'like', '%login%failed%')
                    ->orWhere('action', 'like', '%failed%login%');
            })
            ->where('created_at', '>=', now()->subDay())
            ->select('id', 'user_id', 'ip_address', 'created_at', 'metadata')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(fn ($log) => [
                'id' => $log->id,
                'user_id' => $log->user_id,
                'ip_address' => $log->ip_address,
                'created_at' => $log->created_at->toIso8601String(),
                'email' => $log->metadata['email'] ?? null,
            ]);

        // Security events (last 7 days)
        $dateExpr = $this->dateFormat('created_at');
        $securityEvents = AuditLog::query()
            ->whereIn('action', [
                'login.failed',
                'login.success',
                'two_factor.enabled',
                'two_factor.disabled',
                'password.changed',
                'admin.action',
            ])
            ->where('created_at', '>=', now()->subDays(7))
            ->select(
                'action',
                DB::raw('COUNT(*) as count'),
                DB::raw("{$dateExpr} as date")
            )
            ->groupBy('action', DB::raw($dateExpr))
            ->orderByDesc('date')
            ->get()
            ->groupBy('action')
            ->map(fn ($group) => $group->map(fn ($item) => [
                'date' => $item->date,
                'count' => (int) $item->count,
            ]));

        // Active sessions count (approximate from sessions table)
        $activeSessions = DB::table('sessions')
            ->where('last_activity', '>=', now()->subMinutes(config('session.lifetime', 120)))
            ->count();

        // IP addresses with most failed attempts
        $suspiciousIPs = AuditLog::query()
            ->where(function ($query) {
                $query->where('action', 'like', '%login%failed%')
                    ->orWhere('action', 'like', '%failed%login%');
            })
            ->where('created_at', '>=', now()->subDays(7))
            ->select('ip_address', DB::raw('COUNT(*) as attempt_count'))
            ->groupBy('ip_address')
            ->orderByDesc('attempt_count')
            ->limit(10)
            ->get()
            ->map(fn ($item) => [
                'ip_address' => $item->ip_address,
                'attempt_count' => (int) $item->attempt_count,
            ]);

        // Two-factor authentication statistics
        $twoFactorStats = [
            'enabled' => AuditLog::where('action', 'two_factor.enabled')->count(),
            'disabled' => AuditLog::where('action', 'two_factor.disabled')->count(),
        ];

        return Inertia::render('admin/security/index', [
            'rateLimitConfig' => $rateLimitConfig,
            'failedLogins' => $failedLogins,
            'securityEvents' => $securityEvents,
            'activeSessions' => $activeSessions,
            'suspiciousIPs' => $suspiciousIPs,
            'twoFactorStats' => $twoFactorStats,
        ]);
    }

    /**
     * Update rate limit configuration.
     */
    public function updateRateLimit(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'key' => ['required', 'string'],
            'max_attempts' => ['required', 'integer', 'min:1', 'max:1000'],
            'decay_seconds' => ['required', 'integer', 'min:60', 'max:3600'],
        ]);

        // Rate limits are typically configured in code, but we can log the attempt
        $this->auditService->log('admin.security.rate_limit_updated', null, null, null, null, null, null, [
            'rate_limit_key' => $validated['key'],
            'max_attempts' => $validated['max_attempts'],
            'decay_seconds' => $validated['decay_seconds'],
        ]);

        return back()->with('success', 'Rate limit configuration updated (note: actual limits are configured in code).');
    }

    /**
     * Clear rate limit for a specific key.
     */
    public function clearRateLimit(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'key' => ['required', 'string'],
            'identifier' => ['required', 'string'],
        ]);

        RateLimiter::clear($validated['key'].':'.$validated['identifier']);

        $this->auditService->log('admin.security.rate_limit_cleared', null, null, null, null, null, null, [
            'rate_limit_key' => $validated['key'],
            'identifier' => $validated['identifier'],
        ]);

        return back()->with('success', 'Rate limit cleared successfully.');
    }

    /**
     * Get rate limit configuration.
     */
    private function getRateLimitConfig(): array
    {
        // Get rate limit configurations from Fortify and other sources
        return [
            'login' => [
                'max_attempts' => config('fortify.limiters.login', 5),
                'decay_minutes' => 1,
                'description' => 'Login attempts per minute',
            ],
            'two_factor' => [
                'max_attempts' => config('fortify.limiters.two-factor', 5),
                'decay_minutes' => 1,
                'description' => 'Two-factor authentication attempts per minute',
            ],
            'email_otp' => [
                'max_attempts' => 5,
                'decay_minutes' => 15,
                'description' => 'Email OTP requests per 15 minutes',
            ],
            'whatsapp_otp' => [
                'max_attempts' => 5,
                'decay_minutes' => 15,
                'description' => 'WhatsApp OTP requests per 15 minutes',
            ],
        ];
    }
}
