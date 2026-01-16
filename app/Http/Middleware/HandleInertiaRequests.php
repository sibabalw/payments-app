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
            $businessesCount = $user->businesses()->count() + $user->ownedBusinesses()->count();

            // Get all businesses user has access to
            $owned = $user->ownedBusinesses()->get();
            $associated = $user->businesses()->get();
            $allBusinesses = $owned->merge($associated)->unique('id');

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
            })->values()->toArray();

            // Get current business
            if ($user->current_business_id) {
                $current = $allBusinesses->firstWhere('id', $user->current_business_id);
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

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'auth' => [
                'user' => $user,
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'businessesCount' => $businessesCount,
            'currentBusiness' => $currentBusiness,
            'userBusinesses' => $userBusinesses,
        ];
    }
}
