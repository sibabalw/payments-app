<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AiServerAuth
{
    /**
     * Handle an incoming request.
     *
     * Validates that the request comes from the authorized AI MVP server.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = $request->header('X-AI-Server-Key') ?? $request->header('Authorization');

        // Remove "Bearer " prefix if present
        if ($apiKey && str_starts_with($apiKey, 'Bearer ')) {
            $apiKey = substr($apiKey, 7);
        }

        $expectedKey = config('services.ai_mvp_server.api_key');

        if (! $expectedKey || $apiKey !== $expectedKey) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Invalid or missing AI server API key',
            ], 401);
        }

        return $next($request);
    }
}
