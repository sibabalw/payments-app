<?php

namespace App\Jobs;

use App\Models\PayrollJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DetectStuckPayrollJobs implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The maximum number of seconds a job can run before timing out.
     */
    public int $timeout = 300; // 5 minutes

    /**
     * Hours after which a "processing" job is considered stuck.
     */
    protected int $stuckTimeoutHours;

    /**
     * Create a new job instance.
     */
    public function __construct(?int $stuckTimeoutHours = null)
    {
        $this->stuckTimeoutHours = $stuckTimeoutHours ?? (int) config('payroll.stuck_job_timeout_hours', 2); // Default 2 hours
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $cutoffTime = now()->subHours($this->stuckTimeoutHours);

        Log::info('Starting stuck payroll job detection', [
            'stuck_timeout_hours' => $this->stuckTimeoutHours,
            'cutoff_time' => $cutoffTime,
        ]);

        // Find jobs stuck in "processing" status for too long
        $stuckJobs = PayrollJob::where('status', 'processing')
            ->where(function ($query) use ($cutoffTime) {
                // Jobs that have been processing for too long
                $query->where('updated_at', '<=', $cutoffTime)
                    // Or jobs without updated_at but with old created_at
                    ->orWhere(function ($q) use ($cutoffTime) {
                        $q->whereNull('updated_at')
                            ->where('created_at', '<=', $cutoffTime);
                    });
            })
            ->with(['payrollSchedule.business', 'employee'])
            ->get();

        if ($stuckJobs->isEmpty()) {
            Log::info('No stuck payroll jobs detected');

            return;
        }

        Log::warning('Stuck payroll jobs detected', [
            'count' => $stuckJobs->count(),
            'stuck_timeout_hours' => $this->stuckTimeoutHours,
        ]);

        $recovered = 0;
        $errors = 0;

        foreach ($stuckJobs as $job) {
            try {
                DB::transaction(function () use ($job, &$recovered) {
                    // Reload with lock to prevent concurrent recovery
                    $lockedJob = PayrollJob::where('id', $job->id)
                        ->lockForUpdate()
                        ->first();

                    if (! $lockedJob || $lockedJob->status !== 'processing') {
                        // Already recovered or status changed
                        return;
                    }

                    // Check if job is actually stuck (still processing after timeout)
                    $lastUpdate = $lockedJob->updated_at ?? $lockedJob->created_at;
                    if ($lastUpdate->gt(now()->subHours($this->stuckTimeoutHours))) {
                        // Not actually stuck yet
                        return;
                    }

                    // Mark as failed with appropriate error message
                    $errorMessage = sprintf(
                        'Job stuck in processing status for more than %d hours. Last updated: %s',
                        $this->stuckTimeoutHours,
                        $lastUpdate->toDateTimeString()
                    );

                    $lockedJob->updateStatus('failed', $errorMessage);

                    Log::warning('Recovered stuck payroll job', [
                        'payroll_job_id' => $lockedJob->id,
                        'employee_id' => $lockedJob->employee_id,
                        'schedule_id' => $lockedJob->payroll_schedule_id,
                        'last_updated' => $lastUpdate->toDateTimeString(),
                        'stuck_duration_hours' => $lastUpdate->diffInHours(now()),
                    ]);

                    $recovered++;
                });
            } catch (\Exception $e) {
                $errors++;
                Log::error('Failed to recover stuck payroll job', [
                    'payroll_job_id' => $job->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('Stuck payroll job detection completed', [
            'total_stuck' => $stuckJobs->count(),
            'recovered' => $recovered,
            'errors' => $errors,
        ]);

        // Alert if significant number of stuck jobs
        if ($stuckJobs->count() > 10) {
            Log::critical('High number of stuck payroll jobs detected', [
                'count' => $stuckJobs->count(),
                'recovered' => $recovered,
            ]);
        }
    }
}
