<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasBusiness
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        // Check if user has at least one business
        if ($user->businesses()->count() === 0 && $user->ownedBusinesses()->count() === 0) {
            return redirect()->route('businesses.index')
                ->with('warning', 'You need to create a business first.');
        }

        return $next($request);
    }
}
