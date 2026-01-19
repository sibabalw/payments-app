<?php

namespace App\Listeners;

use App\Services\JobSyncService;
use Illuminate\Queue\Events\WorkerStarting;
use Illuminate\Support\Facades\Log;

class SyncJobsOnWorkerStart
{
    /**
     * Handle the event.
     * Syncs pending jobs with queue when worker starts.
     */
    public function handle(WorkerStarting $event): void
    {
        try {
            $jobSyncService = app(JobSyncService::class);
            $results = $jobSyncService->syncAll();

            $totalSynced = $results['payment_jobs']['synced'] + $results['payroll_jobs']['synced'];

            if ($totalSynced > 0) {
                Log::info('Synced pending jobs with queue on worker start', [
                    'payment_jobs_synced' => $results['payment_jobs']['synced'],
                    'payroll_jobs_synced' => $results['payroll_jobs']['synced'],
                    'queue' => $event->queue ?? 'default',
                ]);
            }
        } catch (\Exception $e) {
            // Don't fail worker start if sync fails - log and continue
            Log::warning('Failed to sync jobs on worker start', [
                'error' => $e->getMessage(),
                'queue' => $event->queue ?? 'default',
            ]);
        }
    }
}
