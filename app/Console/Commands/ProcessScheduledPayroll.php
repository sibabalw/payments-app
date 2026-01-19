<?php

namespace App\Console\Commands;

use App\Jobs\ProcessPayrollJob;
use App\Models\PayrollSchedule;
use App\Services\SalaryCalculationService;
use App\Services\SouthAfricanTaxService;
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

    public function __construct(
        protected SouthAfricanTaxService $taxService,
        protected SalaryCalculationService $salaryCalculationService
    ) {
        parent::__construct();
    }

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
            if ($business && ! $business->canPerformActions()) {
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
            // Calculate taxes and custom deductions
            foreach ($employees as $employee) {
                // Calculate gross salary: use time entries if hourly, otherwise use fixed salary
                if ($employee->hourly_rate) {
                    // Calculate salary from time entries
                    $salaryResult = $this->salaryCalculationService->calculateMonthlySalary(
                        $employee,
                        $payPeriodStart,
                        $payPeriodEnd
                    );
                    $grossSalary = $salaryResult['gross_salary'];
                } else {
                    // Use fixed gross salary
                    $grossSalary = $employee->gross_salary;
                }

                // Get all deductions for this employee (company-wide + employee-specific)
                $customDeductions = $employee->getAllDeductions();

                // Calculate net salary with all deductions
                // Check if employee is exempt from UIF (works < 24 hours/month)
                $uifExempt = $employee->isUIFExempt();
                $breakdown = $this->taxService->calculateNetSalary($grossSalary, [
                    'custom_deductions' => $customDeductions,
                    'uif_exempt' => $uifExempt,
                ]);

                // Log the calculation details for verification
                Log::info('Payroll job calculation', [
                    'employee_id' => $employee->id,
                    'employee_name' => $employee->name,
                    'gross_salary' => $grossSalary,
                    'paye_amount' => $breakdown['paye'],
                    'uif_amount' => $breakdown['uif'],
                    'uif_exempt' => $uifExempt,
                    'sdl_amount' => $breakdown['sdl'],
                    'custom_deductions_count' => count($breakdown['custom_deductions'] ?? []),
                    'custom_deductions_total' => $breakdown['custom_deductions_total'] ?? 0,
                    'net_salary' => $breakdown['net'],
                    'company_wide_deductions' => $customDeductions->filter(fn ($d) => $d->employee_id === null)->count(),
                    'employee_specific_deductions' => $customDeductions->filter(fn ($d) => $d->employee_id !== null)->count(),
                ]);

                $payrollJob = $schedule->payrollJobs()->create([
                    'employee_id' => $employee->id,
                    'gross_salary' => $grossSalary,
                    'paye_amount' => $breakdown['paye'],
                    'uif_amount' => $breakdown['uif'],
                    'sdl_amount' => $breakdown['sdl'],
                    'custom_deductions' => $breakdown['custom_deductions'] ?? [],
                    'net_salary' => $breakdown['net'],
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
                    $nextRun = $cron->getNextRunDate(now(config('app.timezone')));

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
