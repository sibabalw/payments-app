<?php

namespace App\Jobs;

use App\Mail\EscrowBalanceWarningEmail;
use App\Models\Business;
use App\Models\PaymentSchedule;
use App\Models\PayrollSchedule;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class CheckEscrowBalanceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 60;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('CheckEscrowBalanceJob: Starting daily escrow balance check');

        // Get all active businesses
        $businesses = Business::active()->with('owner')->get();

        foreach ($businesses as $business) {
            $this->checkBusinessEscrowBalance($business);
        }

        Log::info('CheckEscrowBalanceJob: Completed daily escrow balance check');
    }

    /**
     * Check escrow balance for a specific business
     */
    protected function checkBusinessEscrowBalance(Business $business): void
    {
        $escrowBalance = (float) $business->escrow_balance;

        // Calculate upcoming payment amounts
        $upcomingPaymentAmount = $this->calculateUpcomingPayments($business);

        // Calculate upcoming payroll amounts
        $upcomingPayrollAmount = $this->calculateUpcomingPayroll($business);

        // Total required amount
        $totalRequired = $upcomingPaymentAmount + $upcomingPayrollAmount;

        // Check if escrow balance is insufficient
        if ($totalRequired > $escrowBalance && $totalRequired > 0) {
            $shortfall = $totalRequired - $escrowBalance;

            Log::warning('CheckEscrowBalanceJob: Business has insufficient escrow balance', [
                'business_id' => $business->id,
                'business_name' => $business->name,
                'escrow_balance' => $escrowBalance,
                'upcoming_payments' => $upcomingPaymentAmount,
                'upcoming_payroll' => $upcomingPayrollAmount,
                'total_required' => $totalRequired,
                'shortfall' => $shortfall,
            ]);

            // Send warning email to business owner
            $this->sendWarningEmail($business, $escrowBalance, $totalRequired, $upcomingPaymentAmount, $upcomingPayrollAmount);
        }
    }

    /**
     * Calculate total amount for upcoming payments (within next 7 days)
     */
    protected function calculateUpcomingPayments(Business $business): float
    {
        $upcomingDate = now()->addDays(7);

        // Get active payment schedules with next_run_at within the next 7 days
        $paymentSchedules = PaymentSchedule::where('business_id', $business->id)
            ->active()
            ->where('next_run_at', '<=', $upcomingDate)
            ->with('recipients')
            ->get();

        $total = 0;

        foreach ($paymentSchedules as $schedule) {
            // Each recipient gets the schedule amount
            $recipientCount = $schedule->recipients()->count();
            $total += (float) $schedule->amount * max($recipientCount, 1);
        }

        return $total;
    }

    /**
     * Calculate total amount for upcoming payroll (within next 7 days)
     */
    protected function calculateUpcomingPayroll(Business $business): float
    {
        $upcomingDate = now()->addDays(7);

        // Get active payroll schedules with next_run_at within the next 7 days
        $payrollSchedules = PayrollSchedule::where('business_id', $business->id)
            ->active()
            ->where('next_run_at', '<=', $upcomingDate)
            ->with('employees')
            ->get();

        $total = 0;

        foreach ($payrollSchedules as $schedule) {
            // Sum up gross salaries of all employees in this schedule
            foreach ($schedule->employees as $employee) {
                // Use gross_salary as the base amount
                // In reality, net salary after deductions would be paid, but gross is a safe upper bound
                $total += (float) $employee->gross_salary;
            }
        }

        return $total;
    }

    /**
     * Send warning email to business owner
     */
    protected function sendWarningEmail(
        Business $business,
        float $currentBalance,
        float $totalRequired,
        float $upcomingPayments,
        float $upcomingPayroll
    ): void {
        $owner = $business->owner;

        if (! $owner) {
            Log::warning('CheckEscrowBalanceJob: Business has no owner, skipping email', [
                'business_id' => $business->id,
            ]);

            return;
        }

        try {
            Mail::to($owner->email)->send(new EscrowBalanceWarningEmail(
                user: $owner,
                business: $business,
                currentBalance: $currentBalance,
                totalRequired: $totalRequired,
                upcomingPayments: $upcomingPayments,
                upcomingPayroll: $upcomingPayroll
            ));

            Log::info('CheckEscrowBalanceJob: Warning email sent', [
                'business_id' => $business->id,
                'owner_email' => $owner->email,
            ]);
        } catch (\Exception $e) {
            Log::error('CheckEscrowBalanceJob: Failed to send warning email', [
                'business_id' => $business->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
