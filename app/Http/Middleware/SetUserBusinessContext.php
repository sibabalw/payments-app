<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class SetUserBusinessContext
{
    /**
     * Handle an incoming request.
     *
     * Cache user's business IDs and current business ID for the request lifecycle.
     * This prevents repeated database queries in controllers.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            $user = Auth::user();

            // Cache business IDs for this request
            // Controllers can access via $request->get('user_business_ids')
            $businessIds = $user->businesses()->pluck('businesses.id');
            $request->attributes->set('user_business_ids', $businessIds);

            // Also cache current business ID
            $currentBusinessId = $user->current_business_id ?? session('current_business_id');
            $request->attributes->set('current_business_id', $currentBusinessId);
        }

        return $next($request);
    }
}
