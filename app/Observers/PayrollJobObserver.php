<?php

namespace App\Observers;

use App\Models\PayrollJob;
use App\Services\AuditService;
use App\Services\EscrowService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PayrollJobObserver
{
    public function __construct(
        protected AuditService $auditService,
        protected EscrowService $escrowService
    ) {}

    /**
     * Handle the PayrollJob "creating" event.
     * CRITICAL: Check escrow balance before job creation as final application-level defense.
     */
    public function creating(PayrollJob $payrollJob): void
    {
        // Only check if job has a schedule (bulk inserts might not have relationships loaded)
        if (! $payrollJob->payroll_schedule_id) {
            return;
        }

        // Load business through schedule relationship
        $schedule = $payrollJob->payrollSchedule ?? \App\Models\PayrollSchedule::find($payrollJob->payroll_schedule_id);
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
            Log::warning('PayrollJobObserver: Business not found for payroll job', [
                'payroll_job_id' => $payrollJob->id ?? 'new',
                'schedule_id' => $payrollJob->payroll_schedule_id,
            ]);

            return;
        }

        // CRITICAL CHECK #1: escrow_balance must be NOT NULL
        // This prevents creating jobs when balance is NULL (should be prevented by database constraint, but check here too)
        if ($business->escrow_balance === null) {
            Log::error('PayrollJobObserver: Attempted to create payroll job with NULL escrow balance', [
                'payroll_job_id' => $payrollJob->id ?? 'new',
                'schedule_id' => $payrollJob->payroll_schedule_id,
                'business_id' => $business->id,
                'escrow_balance' => null,
                'required_amount' => $payrollJob->net_salary,
            ]);

            throw new \RuntimeException("Cannot create payroll job: escrow balance is NULL. Business ID: {$business->id}. This is a critical error - active businesses must have a non-NULL escrow balance.");
        }

        $availableBalance = $this->escrowService->getAvailableBalance($business, false, false);

        // CRITICAL CHECK #2: escrow balance must be greater than zero (explicit zero check)
        // This prevents creating jobs when balance is exactly zero
        if ($availableBalance === 0) {
            Log::error('PayrollJobObserver: Attempted to create payroll job with zero balance', [
                'payroll_job_id' => $payrollJob->id ?? 'new',
                'schedule_id' => $payrollJob->payroll_schedule_id,
                'business_id' => $business->id,
                'available_balance' => $availableBalance,
                'required_amount' => $payrollJob->net_salary,
            ]);

            throw new \RuntimeException("Cannot create payroll job: escrow balance is zero. Business ID: {$business->id}, Available: {$availableBalance}");
        }

        // CRITICAL CHECK #3: available balance must not be negative
        if ($availableBalance < 0) {
            Log::error('PayrollJobObserver: Attempted to create payroll job with negative balance', [
                'payroll_job_id' => $payrollJob->id ?? 'new',
                'schedule_id' => $payrollJob->payroll_schedule_id,
                'business_id' => $business->id,
                'available_balance' => $availableBalance,
                'required_amount' => $payrollJob->net_salary,
            ]);

            throw new \RuntimeException("Cannot create payroll job: escrow balance is negative. Business ID: {$business->id}, Available: {$availableBalance}");
        }

        // CRITICAL CHECK #4: available balance must be sufficient for job amount (use net_salary as that's what's actually paid)
        if ($availableBalance < $payrollJob->net_salary) {
            Log::error('PayrollJobObserver: Attempted to create payroll job with insufficient balance', [
                'payroll_job_id' => $payrollJob->id ?? 'new',
                'schedule_id' => $payrollJob->payroll_schedule_id,
                'business_id' => $business->id,
                'available_balance' => $availableBalance,
                'required_amount' => $payrollJob->net_salary,
                'shortfall' => $payrollJob->net_salary - $availableBalance,
            ]);

            throw new \RuntimeException("Cannot create payroll job: insufficient escrow balance. Business ID: {$business->id}, Available: {$availableBalance}, Required: {$payrollJob->net_salary}");
        }

        // CRITICAL CHECK #5: Calculate total pending jobs amount (including this new job) to prevent bulk creation exceeding balance
        // This provides application-level defense even if database triggers are bypassed
        // Use net_salary as that's what's actually paid out to employees
        // Note: FOR UPDATE cannot be used with sum() on PostgreSQL; business row lock above serializes balance checks
        $totalPendingAmount = DB::transaction(function () use ($schedule, $payrollJob) {
            return DB::table('payroll_jobs')
                ->join('payroll_schedules', 'payroll_schedules.id', '=', 'payroll_jobs.payroll_schedule_id')
                ->where('payroll_schedules.business_id', $schedule->business_id)
                ->where('payroll_jobs.status', 'pending')
                ->sum('payroll_jobs.net_salary') + $payrollJob->net_salary;
        });

        // CRITICAL CHECK #6: available balance must be sufficient for total pending jobs (including this one)
        // This prevents creating multiple jobs that together exceed available balance
        if ($availableBalance < $totalPendingAmount) {
            Log::error('PayrollJobObserver: Attempted to create payroll job - total pending jobs would exceed available balance', [
                'payroll_job_id' => $payrollJob->id ?? 'new',
                'schedule_id' => $payrollJob->payroll_schedule_id,
                'business_id' => $business->id,
                'available_balance' => $availableBalance,
                'total_pending_amount' => $totalPendingAmount,
                'current_job_net_salary' => $payrollJob->net_salary,
                'shortfall' => $totalPendingAmount - $availableBalance,
            ]);

            throw new \RuntimeException("Cannot create payroll job: total pending jobs would exceed available balance. Business ID: {$business->id}, Available: {$availableBalance}, Total pending (including this): {$totalPendingAmount}");
        }
    }

    /**
     * Handle the PayrollJob "created" event.
     */
    public function created(PayrollJob $payrollJob): void
    {
        $this->auditService->log(
            'payroll_job.created',
            $payrollJob,
            $payrollJob->getAttributes()
        );
    }

    /**
     * Handle the PayrollJob "updated" event.
     */
    public function updated(PayrollJob $payrollJob): void
    {
        // Only log status changes to avoid too many audit entries
        if ($payrollJob->wasChanged('status')) {
            $this->auditService->log(
                'payroll_job.status_changed',
                $payrollJob,
                [
                    'old_status' => $payrollJob->getOriginal('status'),
                    'new_status' => $payrollJob->status,
                ]
            );
        }
    }
}
