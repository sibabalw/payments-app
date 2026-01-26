<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Inertia\Inertia;
use Inertia\Response;

class QueueController extends Controller
{
    /**
     * Display queue management page.
     */
    public function index(): Response
    {
        $queueConfig = [
            'default' => config('queue.default'),
            'driver' => config('queue.connections.'.config('queue.default').'.driver'),
            'connection' => config('queue.default'),
        ];

        // Get queue statistics (if using database driver)
        $queueStats = [
            'failed_jobs_count' => 0,
            'pending_jobs_count' => 0,
        ];

        if ($queueConfig['driver'] === 'database') {
            try {
                $queueStats['failed_jobs_count'] = \DB::table('failed_jobs')->count();
                $queueStats['pending_jobs_count'] = \DB::table('jobs')->count();
            } catch (\Exception $e) {
                // Table might not exist
            }
        }

        return Inertia::render('admin/queue/index', [
            'queueConfig' => $queueConfig,
            'queueStats' => $queueStats,
        ]);
    }

    /**
     * Restart queue workers.
     */
    public function restart(): RedirectResponse
    {
        Artisan::call('queue:restart');

        return back()->with('success', 'Queue workers will restart after processing current jobs.');
    }

    /**
     * Clear failed jobs.
     */
    public function clearFailed(): RedirectResponse
    {
        Artisan::call('queue:flush');

        return back()->with('success', 'Failed jobs cleared successfully.');
    }
}
