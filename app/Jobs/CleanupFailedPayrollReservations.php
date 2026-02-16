<?php

namespace App\Jobs;

use App\Models\PayrollJob;
use App\Services\EscrowService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CleanupFailedPayrollReservations implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of seconds to wait before considering a failed job's reservation stale.
     */
    protected int $timeoutSeconds;

    /**
     * Create a new job instance.
     */
    public function __construct(?int $timeoutSeconds = null)
    {
        $this->timeoutSeconds = $timeoutSeconds ?? (int) config('payroll.reservation_cleanup_timeout', 3600); // Default 1 hour
    }

    /**
     * Execute the job.
     */
    public function handle(EscrowService $escrowService): void
    {
        $cutoffTime = now()->subSeconds($this->timeoutSeconds);

        // Find failed payroll jobs with reserved funds that have been failed for longer than timeout
        $staleReservations = PayrollJob::where('status', 'failed')
            ->whereNotNull('escrow_deposit_id')
            ->whereNotNull('processed_at')
            ->where('processed_at', '<=', $cutoffTime)
            ->whereNull('funds_returned_manually_at') // Don't cleanup if already manually returned
            ->with(['payrollSchedule.business'])
            ->get();

        // Also find jobs with orphaned reservations (status changed but deposit still linked)
        $orphanedReservations = PayrollJob::whereIn('status', ['pending', 'succeeded'])
            ->whereNotNull('escrow_deposit_id')
            ->where(function ($query) {
                // Jobs that succeeded but shouldn't have reservations (should have been cleared)
                $query->where('status', 'succeeded')
                    ->whereNotNull('processed_at')
                    ->where('processed_at', '<=', now()->subHours(24)); // Only check old succeeded jobs
            })
            ->orWhere(function ($query) use ($cutoffTime) {
                // Pending jobs that have been pending too long with reservations
                $query->where('status', 'pending')
                    ->where('created_at', '<=', $cutoffTime);
            })
            ->with(['payrollSchedule.business'])
            ->get();

        $allStaleReservations = $staleReservations->merge($orphanedReservations)->unique('id');

        if ($allStaleReservations->isEmpty()) {
            Log::info('No stale payroll reservations to cleanup');

            return;
        }

        Log::info('Cleaning up stale payroll reservations', [
            'count' => $allStaleReservations->count(),
            'failed_count' => $staleReservations->count(),
            'orphaned_count' => $orphanedReservations->count(),
            'timeout_seconds' => $this->timeoutSeconds,
        ]);

        $cleanedUp = 0;
        $errors = 0;

        foreach ($allStaleReservations as $payrollJob) {
            try {
                DB::transaction(function () use ($payrollJob, $escrowService) {
                    // Reload with lock to prevent concurrent cleanup
                    $lockedJob = PayrollJob::where('id', $payrollJob->id)
                        ->lockForUpdate()
                        ->first();

                    if (! $lockedJob || ! $lockedJob->escrow_deposit_id) {
                        // Already cleaned up
                        return;
                    }

                    // Only cleanup if status is failed, or if it's an orphaned reservation
                    $isOrphaned = $lockedJob->status === 'succeeded' && $lockedJob->processed_at && $lockedJob->processed_at->lt(now()->subHours(24));
                    $isStalePending = $lockedJob->status === 'pending' && $lockedJob->created_at->lt(now()->subSeconds($this->timeoutSeconds));

                    if ($lockedJob->status !== 'failed' && ! $isOrphaned && ! $isStalePending) {
                        // Status changed or not stale enough
                        return;
                    }

                    $business = $lockedJob->payrollSchedule->business;

                    // Release the reservation by clearing escrow_deposit_id
                    // The escrow balance will be recalculated correctly on next access
                    $lockedJob->update([
                        'escrow_deposit_id' => null,
                    ]);

                    // Increment escrow balance to return the reserved funds
                    // Use net_salary as that's what was reserved
                    $escrowService->incrementBalance($business, $lockedJob->net_salary);

                    Log::info('Released stale payroll reservation', [
                        'payroll_job_id' => $lockedJob->id,
                        'employee_id' => $lockedJob->employee_id,
                        'net_salary' => $lockedJob->net_salary,
                        'business_id' => $business->id,
                        'failed_at' => $lockedJob->processed_at,
                    ]);
                });

                $cleanedUp++;
            } catch (\Exception $e) {
                $errors++;
                Log::error('Failed to cleanup stale payroll reservation', [
                    'payroll_job_id' => $payrollJob->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('Payroll reservation cleanup completed', [
            'total' => $allStaleReservations->count(),
            'cleaned_up' => $cleanedUp,
            'errors' => $errors,
        ]);
    }
}
