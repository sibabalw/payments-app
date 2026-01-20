<?php

namespace App\Services;

use App\Jobs\ProcessPaymentJob;
use App\Jobs\ProcessPayrollJob;
use App\Models\PaymentJob;
use App\Models\PayrollJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class JobSyncService
{
    /**
     * Sync all pending payment and payroll jobs with the queue.
     * Re-dispatches any jobs that are not in the queue.
     */
    public function syncAll(): array
    {
        return [
            'payment_jobs' => $this->syncPaymentJobs(),
            'payroll_jobs' => $this->syncPayrollJobs(),
        ];
    }

    /**
     * Sync pending payment jobs with the queue.
     */
    public function syncPaymentJobs(): array
    {
        $synced = 0;
        $skipped = 0;
        $errors = 0;

        // Get all pending payment jobs
        $pendingJobs = PaymentJob::where('status', 'pending')
            ->whereNull('processed_at')
            ->get();

        foreach ($pendingJobs as $paymentJob) {
            try {
                // Check if job already exists in queue
                if ($this->jobExistsInQueue(ProcessPaymentJob::class, $paymentJob->id)) {
                    $skipped++;

                    continue;
                }

                // Re-dispatch the job
                ProcessPaymentJob::dispatch($paymentJob);
                $synced++;

                Log::info('Re-dispatched payment job to queue', [
                    'payment_job_id' => $paymentJob->id,
                ]);
            } catch (\Exception $e) {
                $errors++;
                Log::error('Failed to re-dispatch payment job', [
                    'payment_job_id' => $paymentJob->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            'total' => $pendingJobs->count(),
            'synced' => $synced,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }

    /**
     * Sync pending payroll jobs with the queue.
     */
    public function syncPayrollJobs(): array
    {
        $synced = 0;
        $skipped = 0;
        $errors = 0;

        // Get all pending payroll jobs
        $pendingJobs = PayrollJob::where('status', 'pending')
            ->whereNull('processed_at')
            ->get();

        foreach ($pendingJobs as $payrollJob) {
            try {
                // Check if job already exists in queue
                if ($this->jobExistsInQueue(ProcessPayrollJob::class, $payrollJob->id)) {
                    $skipped++;

                    continue;
                }

                // Re-dispatch the job
                ProcessPayrollJob::dispatch($payrollJob);
                $synced++;

                Log::info('Re-dispatched payroll job to queue', [
                    'payroll_job_id' => $payrollJob->id,
                ]);
            } catch (\Exception $e) {
                $errors++;
                Log::error('Failed to re-dispatch payroll job', [
                    'payroll_job_id' => $payrollJob->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            'total' => $pendingJobs->count(),
            'synced' => $synced,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }

    /**
     * Check if a job exists in the queue by checking the payload.
     * Uses a simpler approach: just check if job class exists in payload.
     */
    protected function jobExistsInQueue(string $jobClass, int $jobId): bool
    {
        $queueTable = config('queue.connections.database.table', 'jobs');
        $queueName = config('queue.connections.database.queue', 'default');

        try {
            // Get jobs from queue that haven't been processed yet
            $jobs = DB::table($queueTable)
                ->where('queue', $queueName)
                ->whereNull('reserved_at') // Not currently being processed
                ->get();

            foreach ($jobs as $job) {
                $payload = json_decode($job->payload, true);

                if (! isset($payload['data']['commandName'])) {
                    continue;
                }

                // Check if this is the job class we're looking for
                if (str_contains($payload['data']['commandName'], $jobClass)) {
                    try {
                        // Try to deserialize and check the job ID
                        $command = unserialize($payload['data']['command']);

                        if ($command instanceof ProcessPaymentJob && isset($command->paymentJob)) {
                            if ($command->paymentJob->id === $jobId) {
                                return true;
                            }
                        } elseif ($command instanceof ProcessPayrollJob && isset($command->payrollJob)) {
                            if ($command->payrollJob->id === $jobId) {
                                return true;
                            }
                        }
                    } catch (\Exception $e) {
                        // If deserialization fails, continue checking other jobs
                        continue;
                    }
                }
            }
        } catch (\Exception $e) {
            Log::warning('Error checking queue for job', [
                'job_class' => $jobClass,
                'job_id' => $jobId,
                'error' => $e->getMessage(),
            ]);
        }

        return false;
    }
}
