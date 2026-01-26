<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Inertia\Inertia;
use Inertia\Response;

class LogsController extends Controller
{
    /**
     * Display application logs.
     */
    public function index(Request $request): Response
    {
        $logPath = storage_path('logs/laravel.log');
        $lines = (int) $request->get('lines', 100);
        $lines = min(max($lines, 10), 1000); // Between 10 and 1000

        $logContent = [];
        $logSize = 0;
        $exists = false;

        if (File::exists($logPath)) {
            $exists = true;
            $logSize = File::size($logPath);
            $logContent = $this->readLogFile($logPath, $lines);
        }

        return Inertia::render('admin/logs/index', [
            'logExists' => $exists,
            'logSize' => $this->formatBytes($logSize),
            'logContent' => $logContent,
            'lines' => $lines,
        ]);
    }

    /**
     * Clear application logs.
     */
    public function clear(): \Illuminate\Http\RedirectResponse
    {
        $logPath = storage_path('logs/laravel.log');

        if (File::exists($logPath)) {
            File::put($logPath, '');
        }

        return back()->with('success', 'Log file cleared successfully.');
    }

    private function readLogFile(string $path, int $lines): array
    {
        $file = new \SplFileObject($path, 'r');
        $file->seek(PHP_INT_MAX);
        $totalLines = $file->key();

        $startLine = max(0, $totalLines - $lines);
        $file->seek($startLine);

        $content = [];
        while (! $file->eof() && count($content) < $lines) {
            $line = $file->current();
            if ($line !== false) {
                $content[] = $line;
            }
            $file->next();
        }

        return array_reverse($content); // Most recent first
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
