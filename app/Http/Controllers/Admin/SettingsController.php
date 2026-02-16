<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * Display the admin settings page.
     */
    public function index(): Response
    {
        $settings = [
            'escrow_account_number' => config('escrow.account_number', ''),
            'escrow_fee_percentage' => config('escrow.fee_percentage', 2.5),
            'maintenance_mode' => app()->isDownForMaintenance(),
            'app_name' => config('app.name'),
            'app_url' => config('app.url'),
        ];

        return Inertia::render('admin/settings/index', [
            'settings' => $settings,
        ]);
    }

    /**
     * Update application settings.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'escrow_fee_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        // For now, we'll just log the settings change attempt
        // In a real app, you'd update environment variables or a settings table
        $this->auditService->log(
            'settings.updated',
            'Admin attempted to update settings',
            null,
            [
                'attempted_changes' => $validated,
            ]
        );

        return back()->with('info', 'Settings configuration requires environment variable updates. Please contact your system administrator.');
    }

    /**
     * Clear application cache.
     */
    public function clearCache(): RedirectResponse
    {
        Cache::flush();

        $this->auditService->log(
            'settings.cache_cleared',
            'Admin cleared application cache',
        );

        return back()->with('success', 'Application cache cleared successfully.');
    }
}
