<?php

namespace App\Observers;

use App\Models\PaymentJob;
use App\Services\AuditService;
use App\Services\EscrowService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentJobObserver
{
    public function __construct(
        protected AuditService $auditService,
        protected EscrowService $escrowService
    ) {}

    /**
     * Handle the PaymentJob "creating" event.
     * CRITICAL: Check escrow balance before job creation as final application-level defense.
     */
    public function creating(PaymentJob $paymentJob): void
    {
        // Only check if job has a schedule (bulk inserts might not have relationships loaded)
        if (! $paymentJob->payment_schedule_id) {
            return;
        }

        // Load business through schedule relationship
        $schedule = $paymentJob->paymentSchedule ?? \App\Models\PaymentSchedule::find($paymentJob->payment_schedule_id);
        if (! $schedule || ! $schedule->business_id) {
            return;
        }

        // Lock business row and check balance
        $business = DB::transaction(function () use ($schedule) {
            return \App\Models\Business::where('id', $schedule->business_id)
                ->lockForUpdate()
                ->first();
        });

        if (! $business) {
            Log::warning('PaymentJobObserver: Business not found for payment job', [
                'payment_job_id' => $paymentJob->id ?? 'new',
                'schedule_id' => $paymentJob->payment_schedule_id,
            ]);

            return;
        }

        // CRITICAL CHECK #1: escrow_balance must be NOT NULL
        // This prevents creating jobs when balance is NULL (should be prevented by database constraint, but check here too)
        if ($business->escrow_balance === null) {
            Log::error('PaymentJobObserver: Attempted to create payment job with NULL escrow balance', [
                'payment_job_id' => $paymentJob->id ?? 'new',
                'schedule_id' => $paymentJob->payment_schedule_id,
                'business_id' => $business->id,
                'escrow_balance' => null,
                'required_amount' => $paymentJob->amount,
            ]);

            throw new \RuntimeException("Cannot create payment job: escrow balance is NULL. Business ID: {$business->id}. This is a critical error - active businesses must have a non-NULL escrow balance.");
        }

        $availableBalance = $this->escrowService->getAvailableBalance($business, false, false);

        // CRITICAL CHECK #2: escrow balance must be greater than zero (explicit zero check)
        // This prevents creating jobs when balance is exactly zero
        if ($availableBalance === 0) {
            Log::error('PaymentJobObserver: Attempted to create payment job with zero balance', [
                'payment_job_id' => $paymentJob->id ?? 'new',
                'schedule_id' => $paymentJob->payment_schedule_id,
                'business_id' => $business->id,
                'available_balance' => $availableBalance,
                'required_amount' => $paymentJob->amount,
            ]);

            throw new \RuntimeException("Cannot create payment job: escrow balance is zero. Business ID: {$business->id}, Available: {$availableBalance}");
        }

        // CRITICAL CHECK #3: available balance must not be negative
        if ($availableBalance < 0) {
            Log::error('PaymentJobObserver: Attempted to create payment job with negative balance', [
                'payment_job_id' => $paymentJob->id ?? 'new',
                'schedule_id' => $paymentJob->payment_schedule_id,
                'business_id' => $business->id,
                'available_balance' => $availableBalance,
                'required_amount' => $paymentJob->amount,
            ]);

            throw new \RuntimeException("Cannot create payment job: escrow balance is negative. Business ID: {$business->id}, Available: {$availableBalance}");
        }

        // CRITICAL CHECK #4: available balance must be sufficient for job amount
        if ($availableBalance < $paymentJob->amount) {
            Log::error('PaymentJobObserver: Attempted to create payment job with insufficient balance', [
                'payment_job_id' => $paymentJob->id ?? 'new',
                'schedule_id' => $paymentJob->payment_schedule_id,
                'business_id' => $business->id,
                'available_balance' => $availableBalance,
                'required_amount' => $paymentJob->amount,
                'shortfall' => $paymentJob->amount - $availableBalance,
            ]);

            throw new \RuntimeException("Cannot create payment job: insufficient escrow balance. Business ID: {$business->id}, Available: {$availableBalance}, Required: {$paymentJob->amount}");
        }

        // CRITICAL CHECK #5: Calculate total pending jobs amount (including this new job) to prevent bulk creation exceeding balance
        // This provides application-level defense even if database triggers are bypassed
        // Lock pending jobs to ensure we see all concurrent inserts
        $totalPendingAmount = DB::transaction(function () use ($schedule, $paymentJob) {
            return DB::table('payment_jobs')
                ->join('payment_schedules', 'payment_schedules.id', '=', 'payment_jobs.payment_schedule_id')
                ->where('payment_schedules.business_id', $schedule->business_id)
                ->where('payment_jobs.status', 'pending')
                ->lockForUpdate()
                ->sum('payment_jobs.amount') + $paymentJob->amount;
        });

        // CRITICAL CHECK #6: available balance must be sufficient for total pending jobs (including this one)
        // This prevents creating multiple jobs that together exceed available balance
        if ($availableBalance < $totalPendingAmount) {
            Log::error('PaymentJobObserver: Attempted to create payment job - total pending jobs would exceed available balance', [
                'payment_job_id' => $paymentJob->id ?? 'new',
                'schedule_id' => $paymentJob->payment_schedule_id,
                'business_id' => $business->id,
                'available_balance' => $availableBalance,
                'total_pending_amount' => $totalPendingAmount,
                'current_job_amount' => $paymentJob->amount,
                'shortfall' => $totalPendingAmount - $availableBalance,
            ]);

            throw new \RuntimeException("Cannot create payment job: total pending jobs would exceed available balance. Business ID: {$business->id}, Available: {$availableBalance}, Total pending (including this): {$totalPendingAmount}");
        }
    }

    /**
     * Handle the PaymentJob "created" event.
     */
    public function created(PaymentJob $paymentJob): void
    {
        $this->auditService->log(
            'payment_job.created',
            $paymentJob,
            $paymentJob->getAttributes()
        );
    }

    /**
     * Handle the PaymentJob "updated" event.
     */
    public function updated(PaymentJob $paymentJob): void
    {
        // Only log status changes to avoid too many audit entries
        if ($paymentJob->wasChanged('status')) {
            $this->auditService->log(
                'payment_job.status_changed',
                $paymentJob,
                [
                    'old_status' => $paymentJob->getOriginal('status'),
                    'new_status' => $paymentJob->status,
                ]
            );
        }
    }
}
