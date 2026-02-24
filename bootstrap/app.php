<?php

use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\EnsureUserIsNotAdmin;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetUserBusinessContext;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        // Trust proxies so X-Forwarded-Proto is respected (fixes signed URL signature mismatch behind HTTPS proxy)
        $middleware->trustProxies(at: '*');

        $middleware->web(append: [
            HandleAppearance::class,
            SetUserBusinessContext::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
            'user' => EnsureUserIsNotAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->report(function (\Throwable $e) {
            $request = request();

            // Skip database error logging for stateless routes (sitemap, health, etc.)
            // These can fail when DB/context assumptions don't apply
            if ($request && (
                $request->is('sitemap.xml') ||
                $request->route()?->getName() === 'sitemap'
            )) {
                \Illuminate\Support\Facades\Log::error($e->getMessage(), [
                    'exception' => get_class($e),
                    'url' => $request->fullUrl(),
                ]);

                return;
            }

            $user = $request?->user();

            // Log error to database and notify admins
            try {
                $errorLogService = app(\App\Services\ErrorLogService::class);
                $errorLogService->logError($e, $request, $user);
            } catch (\Throwable $loggingException) {
                // If error logging itself fails, fall back to Laravel's default logging
                \Illuminate\Support\Facades\Log::error('Failed to log error to database', [
                    'original_error' => $e->getMessage(),
                    'logging_error' => $loggingException->getMessage(),
                ]);
            }
        });
    })->create();
