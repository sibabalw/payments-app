<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Inertia\Inertia;
use Inertia\Response;

class SystemHealthController extends Controller
{
    /**
     * Display system health and monitoring information.
     */
    public function index(): Response
    {
        // Database connection status
        $dbStatus = 'healthy';
        $dbResponseTime = null;
        try {
            $start = microtime(true);
            DB::select('SELECT 1');
            $dbResponseTime = round((microtime(true) - $start) * 1000, 2);
        } catch (\Exception $e) {
            $dbStatus = 'unhealthy';
        }

        // Cache status
        $cacheStatus = 'healthy';
        try {
            Cache::put('health_check', 'ok', 1);
            Cache::get('health_check');
        } catch (\Exception $e) {
            $cacheStatus = 'unhealthy';
        }

        // Queue status
        $queueStatus = 'healthy';
        $queueConnection = config('queue.default');
        $queueDriver = config("queue.connections.{$queueConnection}.driver");

        // System metrics
        $systemMetrics = [
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'timezone' => config('app.timezone'),
            'environment' => config('app.env'),
            'debug_mode' => config('app.debug'),
        ];

        // Database metrics
        $dbMetrics = [
            'connection' => config('database.default'),
            'driver' => config("database.connections.{$dbStatus}.driver"),
            'response_time_ms' => $dbResponseTime,
        ];

        // Cache metrics
        $cacheMetrics = [
            'driver' => config('cache.default'),
            'status' => $cacheStatus,
        ];

        // Queue metrics
        $queueMetrics = [
            'connection' => $queueConnection,
            'driver' => $queueDriver,
            'status' => $queueStatus,
        ];

        // Recent errors (from logs - simplified)
        $recentErrors = [];
        $logPath = storage_path('logs/laravel.log');
        if (file_exists($logPath)) {
            $logSize = filesize($logPath);
            $logSizeFormatted = $this->formatBytes($logSize);
        } else {
            $logSizeFormatted = '0 B';
        }

        return Inertia::render('admin/system-health/index', [
            'dbStatus' => $dbStatus,
            'cacheStatus' => $cacheStatus,
            'queueStatus' => $queueStatus,
            'systemMetrics' => $systemMetrics,
            'dbMetrics' => $dbMetrics,
            'cacheMetrics' => $cacheMetrics,
            'queueMetrics' => $queueMetrics,
            'logSize' => $logSizeFormatted ?? '0 B',
        ]);
    }

    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision).' '.$units[$i];
    }
}
