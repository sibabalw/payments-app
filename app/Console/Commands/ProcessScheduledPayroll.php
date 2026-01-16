<?php

namespace App\Console\Commands;

use App\Jobs\ProcessPayrollJob;
use App\Models\PayrollSchedule;
use Cron\CronExpression;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessScheduledPayroll extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payroll:process-scheduled';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process all due payroll schedules';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dueSchedules = PayrollSchedule::due()
            ->with(['employees', 'business'])
            ->get();

        if ($dueSchedules->isEmpty()) {
            $this->info('No due payroll schedules found.');
            return Command::SUCCESS;
        }

        $this->info("Found {$dueSchedules->count()} due payroll schedule(s).");

        $totalJobs = 0;

        foreach ($dueSchedules as $schedule) {
            // Skip if business is banned or suspended
            $business = $schedule->business;
            if ($business && !$business->canPerformActions()) {
                $this->warn("Schedule #{$schedule->id} belongs to a {$business->status} business. Skipping.");
                continue;
            }

            $employees = $schedule->employees;

            if ($employees->isEmpty()) {
                $this->warn("Schedule #{$schedule->id} has no employees assigned. Skipping.");
                continue;
            }

            // Calculate pay period dates
            $payPeriodStart = now()->startOfMonth();
            $payPeriodEnd = now()->endOfMonth();

            // Create payroll jobs for each employee (only when schedule executes, not during creation)
            // No tax calculations - just record gross salary
            foreach ($employees as $employee) {
                $payrollJob = $schedule->payrollJobs()->create([
                    'employee_id' => $employee->id,
                    'gross_salary' => $employee->gross_salary,
                    'paye_amount' => 0,
                    'uif_amount' => 0,
                    'sdl_amount' => 0,
                    'net_salary' => $employee->gross_salary, // For now, net equals gross (no deductions)
                    'currency' => 'ZAR',
                    'status' => 'pending',
                    'pay_period_start' => $payPeriodStart,
                    'pay_period_end' => $payPeriodEnd,
                ]);

                // Dispatch job to queue (will be stored in jobs table)
                try {
                    ProcessPayrollJob::dispatch($payrollJob);
                    $totalJobs++;
                    
                    Log::info('Payroll job dispatched to queue', [
                        'payroll_job_id' => $payrollJob->id,
                        'employee_id' => $employee->id,
                        'schedule_id' => $schedule->id,
                    ]);
                } catch (\Exception $e) {
                    Log::error('Failed to dispatch payroll job to queue', [
                        'payroll_job_id' => $payrollJob->id,
                        'error' => $e->getMessage(),
                    ]);
                    
                    $this->error("Failed to dispatch payroll job #{$payrollJob->id}: {$e->getMessage()}");
                }
            }

            // Handle one-time vs recurring schedules
            if ($schedule->isOneTime()) {
                // Auto-cancel one-time schedules after execution
                $schedule->update([
                    'status' => 'cancelled',
                    'next_run_at' => null,
                    'last_run_at' => now(),
                ]);

                Log::info('One-time payroll schedule auto-cancelled after execution', [
                    'schedule_id' => $schedule->id,
                ]);

                $this->info("Schedule #{$schedule->id} (one-time) processed and auto-cancelled.");
            } else {
                // Calculate next run time for recurring schedules
                try {
                    $cron = CronExpression::factory($schedule->frequency);
                    $nextRun = $cron->getNextRunDate(now());
                    
                    $schedule->update([
                        'next_run_at' => $nextRun,
                        'last_run_at' => now(),
                    ]);

                    $this->info("Schedule #{$schedule->id} processed. Next run: {$nextRun->format('Y-m-d H:i:s')}");
                } catch (\Exception $e) {
                    Log::error('Failed to calculate next run time for payroll schedule', [
                        'schedule_id' => $schedule->id,
                        'frequency' => $schedule->frequency,
                        'error' => $e->getMessage(),
                    ]);

                    $this->error("Failed to calculate next run time for schedule #{$schedule->id}: {$e->getMessage()}");
                }
            }
        }

        $this->info("Dispatched {$totalJobs} payroll job(s) to the queue.");

        return Command::SUCCESS;
    }
}
