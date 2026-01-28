<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class StorageController extends Controller
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * Display storage management page.
     */
    public function index(): Response
    {
        // Cache statistics
        $cacheDriver = config('cache.default');
        $cacheConfig = config("cache.stores.{$cacheDriver}");

        // Get cache size (approximate for file cache)
        $cacheSize = 0;
        $cacheFileCount = 0;
        if ($cacheDriver === 'file') {
            $cachePath = storage_path('framework/cache/data');
            if (is_dir($cachePath)) {
                $cacheSize = $this->getDirectorySize($cachePath);
                $cacheFileCount = $this->countFiles($cachePath);
            }
        }

        // Session storage
        $sessionDriver = config('session.driver');
        $sessionLifetime = config('session.lifetime');
        $sessionSize = 0;
        $sessionCount = 0;

        if ($sessionDriver === 'file') {
            $sessionPath = storage_path('framework/sessions');
            if (is_dir($sessionPath)) {
                $sessionSize = $this->getDirectorySize($sessionPath);
                $sessionCount = $this->countFiles($sessionPath);
            }
        } elseif ($sessionDriver === 'database') {
            $sessionCount = \DB::table('sessions')->count();
            // Approximate size: count * average session size (estimate 2KB)
            $sessionSize = $sessionCount * 2048;
        }

        // Log file sizes
        $logFiles = $this->getLogFileSizes();

        // Storage disk information
        $storageDisks = $this->getStorageDiskInfo();

        // Total storage usage
        $totalStorage = $cacheSize + $sessionSize + array_sum(array_column($logFiles, 'size'));

        return Inertia::render('admin/storage/index', [
            'cache' => [
                'driver' => $cacheDriver,
                'config' => $cacheConfig,
                'size' => $this->formatBytes($cacheSize),
                'size_bytes' => $cacheSize,
                'file_count' => $cacheFileCount,
            ],
            'sessions' => [
                'driver' => $sessionDriver,
                'lifetime' => $sessionLifetime,
                'size' => $this->formatBytes($sessionSize),
                'size_bytes' => $sessionSize,
                'count' => $sessionCount,
            ],
            'logs' => $logFiles,
            'storage_disks' => $storageDisks,
            'total_storage' => $this->formatBytes($totalStorage),
            'total_storage_bytes' => $totalStorage,
        ]);
    }

    /**
     * Clear application cache.
     */
    public function clearCache(): RedirectResponse
    {
        Artisan::call('cache:clear');
        Artisan::call('config:clear');
        Artisan::call('route:clear');
        Artisan::call('view:clear');

        $this->auditService->log('admin.storage.cache_cleared', null, null, null, null, null, null, [
            'action' => 'clear_cache',
        ]);

        return back()->with('success', 'Cache cleared successfully.');
    }

    /**
     * Clear session storage.
     */
    public function clearSessions(): RedirectResponse
    {
        $sessionDriver = config('session.driver');

        if ($sessionDriver === 'file') {
            $sessionPath = storage_path('framework/sessions');
            if (is_dir($sessionPath)) {
                $files = glob($sessionPath.'/*');
                foreach ($files as $file) {
                    if (is_file($file)) {
                        unlink($file);
                    }
                }
            }
        } elseif ($sessionDriver === 'database') {
            \DB::table('sessions')->truncate();
        }

        $this->auditService->log('admin.storage.sessions_cleared', null, null, null, null, null, null, [
            'action' => 'clear_sessions',
            'driver' => $sessionDriver,
        ]);

        return back()->with('success', 'Sessions cleared successfully.');
    }

    /**
     * Get directory size in bytes.
     */
    private function getDirectorySize(string $directory): int
    {
        $size = 0;
        if (is_dir($directory)) {
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS)
            );

            foreach ($files as $file) {
                if ($file->isFile()) {
                    $size += $file->getSize();
                }
            }
        }

        return $size;
    }

    /**
     * Count files in directory.
     */
    private function countFiles(string $directory): int
    {
        $count = 0;
        if (is_dir($directory)) {
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS)
            );

            foreach ($files as $file) {
                if ($file->isFile()) {
                    $count++;
                }
            }
        }

        return $count;
    }

    /**
     * Get log file sizes.
     */
    private function getLogFileSizes(): array
    {
        $logPath = storage_path('logs');
        $logFiles = [];

        if (is_dir($logPath)) {
            $files = glob($logPath.'/*.log');
            foreach ($files as $file) {
                if (is_file($file)) {
                    $logFiles[] = [
                        'name' => basename($file),
                        'size' => filesize($file),
                        'size_formatted' => $this->formatBytes(filesize($file)),
                        'modified' => date('Y-m-d H:i:s', filemtime($file)),
                    ];
                }
            }
        }

        // Sort by size descending
        usort($logFiles, fn ($a, $b) => $b['size'] <=> $a['size']);

        return $logFiles;
    }

    /**
     * Get storage disk information.
     */
    private function getStorageDiskInfo(): array
    {
        $disks = ['local', 'public'];
        $diskInfo = [];

        foreach ($disks as $diskName) {
            try {
                $disk = Storage::disk($diskName);
                $path = storage_path('app/'.($diskName === 'public' ? 'public' : ''));

                $size = 0;
                $fileCount = 0;

                if (is_dir($path)) {
                    $size = $this->getDirectorySize($path);
                    $fileCount = $this->countFiles($path);
                }

                $diskInfo[] = [
                    'name' => $diskName,
                    'driver' => config("filesystems.disks.{$diskName}.driver", 'local'),
                    'size' => $this->formatBytes($size),
                    'size_bytes' => $size,
                    'file_count' => $fileCount,
                ];
            } catch (\Exception $e) {
                // Disk might not be configured
            }
        }

        return $diskInfo;
    }

    /**
     * Format bytes to human readable format.
     */
    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision).' '.$units[$i];
    }
}
