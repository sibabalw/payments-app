<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Inertia\Inertia;
use Inertia\Response;

class SystemConfigurationController extends Controller
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * Display system configuration page.
     */
    public function index(): Response
    {
        $config = [
            'app' => [
                'name' => config('app.name'),
                'url' => config('app.url'),
                'env' => config('app.env'),
                'debug' => config('app.debug'),
                'timezone' => config('app.timezone'),
                'locale' => config('app.locale'),
            ],
            'database' => [
                'default' => config('database.default'),
                'driver' => config('database.connections.'.config('database.default').'.driver'),
            ],
            'cache' => [
                'default' => config('cache.default'),
            ],
            'queue' => [
                'default' => config('queue.default'),
                'driver' => config('queue.connections.'.config('queue.default').'.driver'),
            ],
            'mail' => [
                'default' => config('mail.default'),
                'mailer' => config('mail.mailers.'.config('mail.default').'.transport'),
            ],
            'session' => [
                'driver' => config('session.driver'),
                'lifetime' => config('session.lifetime'),
            ],
            'maintenance_mode' => app()->isDownForMaintenance(),
        ];

        return Inertia::render('admin/system-configuration/index', [
            'config' => $config,
        ]);
    }

    /**
     * Toggle maintenance mode.
     */
    public function toggleMaintenance(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
        ]);

        if ($validated['enabled']) {
            Artisan::call('down', ['--secret' => config('app.maintenance_secret', 'secret')]);
            $this->auditService->log('system.maintenance.enabled', 'Admin enabled maintenance mode');
        } else {
            Artisan::call('up');
            $this->auditService->log('system.maintenance.disabled', 'Admin disabled maintenance mode');
        }

        return back()->with('success', 'Maintenance mode '.($validated['enabled'] ? 'enabled' : 'disabled').' successfully.');
    }
}
