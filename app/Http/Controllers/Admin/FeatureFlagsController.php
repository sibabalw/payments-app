<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FeatureFlagsController extends Controller
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * Display feature flags page.
     */
    public function index(): Response
    {
        $featureFlags = config('features', []);

        // Get current state from config (not env directly)
        $currentState = [
            'redis' => [
                'enabled' => (bool) config('features.redis.enabled', false),
                'locks' => (bool) config('features.redis.locks', false),
                'idempotency' => (bool) config('features.redis.idempotency', false),
                'queues' => (bool) config('features.redis.queues', false),
            ],
        ];

        // Get usage statistics (if available)
        $usageStats = $this->getUsageStatistics();

        return Inertia::render('admin/feature-flags/index', [
            'featureFlags' => $featureFlags,
            'currentState' => $currentState,
            'usageStats' => $usageStats,
        ]);
    }

    /**
     * Toggle a feature flag.
     */
    public function toggle(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'category' => ['required', 'string'],
            'feature' => ['required', 'string'],
            'enabled' => ['required', 'boolean'],
        ]);

        // Note: In a production environment, feature flags should be managed through
        // a proper feature flag service or database. For now, we'll just log the action
        // and inform the user that they need to update environment variables.

        $this->auditService->log(
            'admin.feature_flag.toggled',
            null,
            null,
            null,
            null,
            null,
            null,
            [
                'category' => $validated['category'],
                'feature' => $validated['feature'],
                'enabled' => $validated['enabled'],
            ]
        );

        $enabledValue = $validated['enabled'] ? 'true' : 'false';
        $envVar = strtoupper("{$validated['category']}_{$validated['feature']}_ENABLED");

        return back()->with(
            'success',
            "Feature flag toggle requested. To apply changes, update the environment variable: {$envVar}={$enabledValue}"
        );
    }

    /**
     * Get usage statistics for feature flags.
     */
    private function getUsageStatistics(): array
    {
        // This would typically query services to see if features are being used
        // For now, return placeholder data
        return [
            'redis' => [
                'enabled' => [
                    'locks_used' => 0,
                    'idempotency_used' => 0,
                    'queues_used' => 0,
                ],
            ],
        ];
    }
}
