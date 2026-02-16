<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');

        $businessesCount = 0;
        $currentBusiness = null;
        $userBusinesses = [];

        if ($user = $request->user()) {
            // Get current business ID from cached middleware data
            $currentBusinessId = $request->attributes->get('current_business_id') ?? $user->current_business_id;

            // Get all businesses user has access to with minimal fields
            $owned = $user->ownedBusinesses()
                ->select(['id', 'name', 'status', 'logo'])
                ->get();
            $associated = $user->businesses()
                ->select(['businesses.id', 'businesses.name', 'businesses.status', 'businesses.logo'])
                ->get();
            $allBusinesses = $owned->merge($associated)->unique('id')->values();

            $businessesCount = $allBusinesses->count();

            $userBusinesses = $allBusinesses->map(function ($business) {
                $logoUrl = null;
                if ($business->logo) {
                    $logoUrl = Storage::disk('public')->url($business->logo);
                }

                return [
                    'id' => $business->id,
                    'name' => $business->name,
                    'status' => $business->status,
                    'logo' => $logoUrl,
                ];
            })->toArray();

            // Get current business
            if ($currentBusinessId) {
                $current = $allBusinesses->firstWhere('id', $currentBusinessId);
                if ($current) {
                    $logoUrl = null;
                    if ($current->logo) {
                        $logoUrl = Storage::disk('public')->url($current->logo);
                    }

                    $currentBusiness = [
                        'id' => $current->id,
                        'name' => $current->name,
                        'status' => $current->status,
                        'logo' => $logoUrl,
                    ];
                }
            }
        }

        $appName = config('app.name');
        $appName = in_array($appName, ['Swift Pay', 'swift pay', 'Swift pay'], true) ? 'SwiftPay' : $appName;

        return [
            ...parent::share($request),
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'info' => fn () => $request->session()->get('info'),
            ],
            'name' => $appName,
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'auth' => [
                'user' => $user,
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'businessesCount' => $businessesCount,
            'currentBusiness' => $currentBusiness,
            'userBusinesses' => $userBusinesses,
            'hasCompletedDashboardTour' => $user?->has_completed_dashboard_tour ?? false,
        ];
    }
}
