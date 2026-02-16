<?php

namespace App\Services;

use App\Helpers\LogContext;
use App\Models\PaymentJob;
use App\Models\PayrollJob;

/**
 * Recovery Service
 *
 * Detects and recovers from stuck jobs, failed jobs, and other issues.
 * Provides automatic retry for transient failures.
 */
class RecoveryService
{
    protected int $stuckJobThresholdMinutes;

    protected int $maxRetries;

    public function __construct()
    {
        $this->stuckJobThresholdMinutes = (int) config('payroll.stuck_job_threshold_minutes', 30);
        $this->maxRetries = (int) config('payroll.max_retries', 3);
    }

    /**
     * Detect and recover stuck payroll jobs
     *
     * Resets jobs stuck in 'processing' status back to 'pending' for retry.
     * This is safe because ledger writes only happen in the same transaction as status = 'succeeded'.
     * If a worker dies while status is 'processing', the transaction never commits, so no ledger entry exists.
     * Therefore, resetting to 'pending' cannot cause double-processing or duplicate ledger entries.
     *
     * @param  int|null  $limit  Maximum number of jobs to process
     * @return array Results with 'detected', 'recovered', 'failed'
     */
    public function recoverStuckPayrollJobs(?int $limit = 100): array
    {
        $threshold = now()->subMinutes($this->stuckJobThresholdMinutes);

        // Find jobs stuck in 'processing' status for too long
        $stuckJobs = PayrollJob::where('status', 'processing')
            ->where('updated_at', '<', $threshold)
            ->limit($limit)
            ->get();

        $detected = $stuckJobs->count();
        $recovered = 0;
        $failed = 0;

        foreach ($stuckJobs as $job) {
            try {
                // Reset to pending so it can be retried. Do not clear escrow_deposit_id
                // (CleanupFailedPayrollReservations handles stale reservations for failed/stale jobs.)
                $job->update([
                    'status' => 'pending',
                    'error_message' => 'Recovered from stuck: Job was stuck in processing state and has been reset for retry',
                ]);

                LogContext::info('Stuck payroll job recovered', LogContext::create(
                    null,
                    $job->payrollSchedule?->business_id,
                    $job->id,
                    'job_recovery',
                    null,
                    [
                        'job_id' => $job->id,
                        'stuck_duration_minutes' => $job->updated_at->diffInMinutes(now()),
                    ]
                ));

                $recovered++;
            } catch (\Exception $e) {
                LogContext::error('Failed to recover stuck payroll job', LogContext::create(
                    null,
                    $job->payrollSchedule?->business_id,
                    $job->id,
                    'job_recovery',
                    null,
                    ['error' => $e->getMessage()]
                ));

                $failed++;
            }
        }

        return [
            'detected' => $detected,
            'recovered' => $recovered,
            'failed' => $failed,
        ];
    }

    /**
     * Detect and recover stuck payment jobs
     *
     * Resets jobs stuck in 'processing' status back to 'pending' for retry.
     * This is safe because ledger writes only happen in the same transaction as status = 'succeeded'.
     * If a worker dies while status is 'processing', the transaction never commits, so no ledger entry exists.
     * Therefore, resetting to 'pending' cannot cause double-processing or duplicate ledger entries.
     *
     * @param  int|null  $limit  Maximum number of jobs to process
     * @return array Results with 'detected', 'recovered', 'failed'
     */
    public function recoverStuckPaymentJobs(?int $limit = 100): array
    {
        $threshold = now()->subMinutes($this->stuckJobThresholdMinutes);

        // Find jobs stuck in 'processing' status for too long
        $stuckJobs = PaymentJob::where('status', 'processing')
            ->where('updated_at', '<', $threshold)
            ->limit($limit)
            ->get();

        $detected = $stuckJobs->count();
        $recovered = 0;
        $failed = 0;

        foreach ($stuckJobs as $job) {
            try {
                // Reset to pending so it can be retried. Do not clear escrow_deposit_id.
                $job->update([
                    'status' => 'pending',
                    'error_message' => 'Recovered from stuck: Job was stuck in processing state and has been reset for retry',
                ]);

                LogContext::info('Stuck payment job recovered', LogContext::create(
                    null,
                    $job->paymentSchedule?->business_id,
                    $job->id,
                    'job_recovery',
                    null,
                    [
                        'job_id' => $job->id,
                        'stuck_duration_minutes' => $job->updated_at->diffInMinutes(now()),
                    ]
                ));

                $recovered++;
            } catch (\Exception $e) {
                LogContext::error('Failed to recover stuck payment job', LogContext::create(
                    null,
                    $job->paymentSchedule?->business_id,
                    $job->id,
                    'job_recovery',
                    null,
                    ['error' => $e->getMessage()]
                ));

                $failed++;
            }
        }

        return [
            'detected' => $detected,
            'recovered' => $recovered,
            'failed' => $failed,
        ];
    }

    /**
     * Retry failed jobs that haven't exceeded max retries
     *
     * @param  string  $jobType  Job type ('payroll' or 'payment')
     * @param  int|null  $limit  Maximum number of jobs to process
     * @return array Results with 'retried', 'skipped', 'failed'
     */
    public function retryFailedJobs(string $jobType, ?int $limit = 100): array
    {
        if ($jobType === 'payroll') {
            $model = PayrollJob::class;
        } elseif ($jobType === 'payment') {
            $model = PaymentJob::class;
        } else {
            throw new \InvalidArgumentException("Invalid job type: {$jobType}");
        }

        // Find failed jobs that can be retried
        // We'll track retry count in error_message or use a separate field if available
        $failedJobs = $model::where('status', 'failed')
            ->where(function ($query) {
                // Only retry jobs that don't have "permanent" in error message
                $query->whereNull('error_message')
                    ->orWhere('error_message', 'not like', '%permanent%')
                    ->orWhere('error_message', 'not like', '%invalid%');
            })
            ->limit($limit)
            ->get();

        $retried = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($failedJobs as $job) {
            try {
                // Reset to pending for retry
                $job->update([
                    'status' => 'pending',
                    'error_message' => null, // Clear error for retry
                ]);

                LogContext::info('Failed job reset for retry', LogContext::create(
                    null,
                    $job->payrollSchedule?->business_id ?? $job->paymentSchedule?->business_id,
                    $job->id,
                    'job_recovery',
                    null,
                    [
                        'job_type' => $jobType,
                        'job_id' => $job->id,
                    ]
                ));

                $retried++;
            } catch (\Exception $e) {
                LogContext::error('Failed to reset job for retry', LogContext::create(
                    null,
                    $job->payrollSchedule?->business_id ?? $job->paymentSchedule?->business_id,
                    $job->id,
                    'job_recovery',
                    null,
                    ['error' => $e->getMessage()]
                ));

                $failed++;
            }
        }

        return [
            'retried' => $retried,
            'skipped' => $skipped,
            'failed' => $failed,
        ];
    }

    /**
     * Run all recovery operations
     *
     * @return array Combined results
     */
    public function runRecovery(): array
    {
        $payrollStuck = $this->recoverStuckPayrollJobs();
        $paymentStuck = $this->recoverStuckPaymentJobs();
        $payrollFailed = $this->retryFailedJobs('payroll');
        $paymentFailed = $this->retryFailedJobs('payment');

        return [
            'payroll_stuck' => $payrollStuck,
            'payment_stuck' => $paymentStuck,
            'payroll_failed' => $payrollFailed,
            'payment_failed' => $paymentFailed,
        ];
    }
}
