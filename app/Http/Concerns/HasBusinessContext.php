<?php

namespace App\Http\Concerns;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

trait HasBusinessContext
{
    /**
     * Get the current business ID from cached request context.
     */
    protected function getCurrentBusinessId(Request $request): ?int
    {
        // Try cached value from middleware first
        $businessId = $request->attributes->get('current_business_id');

        if ($businessId) {
            return $businessId;
        }

        // Fallback to direct query if not cached
        return Auth::user()?->current_business_id ?? session('current_business_id');
    }

    /**
     * Get user's business IDs from cached request context.
     * Returns collection of business IDs the user has access to.
     */
    protected function getUserBusinessIds(Request $request): \Illuminate\Support\Collection
    {
        // Try cached value from middleware first
        $businessIds = $request->attributes->get('user_business_ids');

        if ($businessIds !== null) {
            return $businessIds;
        }

        // Fallback to direct query if not cached
        return Auth::user()->businesses()->pluck('businesses.id');
    }

    /**
     * Get the business ID to use for filtering, with fallback logic.
     * Returns either the requested business_id, current business, or null.
     */
    protected function getFilterBusinessId(Request $request): ?int
    {
        return $request->get('business_id') ?? $this->getCurrentBusinessId($request);
    }
}
