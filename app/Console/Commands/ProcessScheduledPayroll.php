<?php

namespace App\Console\Commands;

use App\Jobs\ProcessPayrollJob;
use App\Models\PayrollSchedule;
use App\Services\AdjustmentService;
use App\Services\SalaryCalculationService;
use App\Services\SouthAfricaHolidayService;
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
        protected SalaryCalculationService $salaryCalculationService,
        protected AdjustmentService $adjustmentService,
        protected SouthAfricaHolidayService $holidayService
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

            // Calculate pay period dates based on schedule type
            // This ensures consistent period calculation between adjustment creation and payroll processing
            $calculatedPeriod = $schedule->calculatePayPeriod();
            $payPeriodStart = $calculatedPeriod['start'];
            $payPeriodEnd = $calculatedPeriod['end'];

            Log::info('Calculated pay period for schedule', [
                'schedule_id' => $schedule->id,
                'schedule_name' => $schedule->name,
                'schedule_type' => $schedule->schedule_type,
                'period_start' => $payPeriodStart->format('Y-m-d'),
                'period_end' => $payPeriodEnd->format('Y-m-d'),
            ]);

            // Create payroll jobs for each employee (only when schedule executes, not during creation)
            // Calculate taxes and apply adjustments
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

                // Check if employee is exempt from UIF (works < 24 hours/month)
                $uifExempt = $employee->isUIFExempt();

                // Calculate net salary after statutory deductions only (PAYE, UIF)
                $breakdown = $this->taxService->calculateNetSalary($grossSalary, [
                    'uif_exempt' => $uifExempt,
                ]);

                // Load valid adjustments for this payroll period and schedule
                $adjustments = $this->adjustmentService->getValidAdjustments(
                    $employee,
                    $payPeriodStart,
                    $payPeriodEnd,
                    $schedule->id
                );

                // Apply adjustments to net salary
                $adjustmentResult = $this->adjustmentService->applyAdjustments(
                    $breakdown['net'],
                    $adjustments,
                    $grossSalary
                );

                // Log the calculation details for verification
                Log::info('Payroll job calculation', [
                    'employee_id' => $employee->id,
                    'employee_name' => $employee->name,
                    'gross_salary' => $grossSalary,
                    'paye_amount' => $breakdown['paye'],
                    'uif_amount' => $breakdown['uif'],
                    'uif_exempt' => $uifExempt,
                    'sdl_amount' => $breakdown['sdl'],
                    'net_after_statutory' => $breakdown['net'],
                    'adjustments_count' => count($adjustmentResult['adjustments_breakdown'] ?? []),
                    'total_adjustments' => $adjustmentResult['total_adjustments'] ?? 0,
                    'total_deductions' => $adjustmentResult['total_deductions'] ?? 0,
                    'total_additions' => $adjustmentResult['total_additions'] ?? 0,
                    'final_net_salary' => $adjustmentResult['final_net_salary'],
                ]);

                $payrollJob = $schedule->payrollJobs()->create([
                    'employee_id' => $employee->id,
                    'gross_salary' => $grossSalary,
                    'paye_amount' => $breakdown['paye'],
                    'uif_amount' => $breakdown['uif'],
                    'sdl_amount' => $breakdown['sdl'],
                    'adjustments' => $adjustmentResult['adjustments_breakdown'] ?? [],
                    'net_salary' => $adjustmentResult['final_net_salary'],
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
                    $nextRun = \Carbon\Carbon::instance($cron->getNextRunDate(now(config('app.timezone'))));

                    // Skip weekends and holidays - move to next business day if needed
                    if (! $this->holidayService->isBusinessDay($nextRun)) {
                        $originalDate = $nextRun->format('Y-m-d');
                        $originalTime = $nextRun->format('H:i');
                        $nextRun = $this->holidayService->getNextBusinessDay($nextRun);
                        // Preserve the time from the original cron calculation
                        $nextRun->setTime((int) explode(':', $originalTime)[0], (int) explode(':', $originalTime)[1]);

                        Log::info('Payroll schedule next run adjusted to skip weekend/holiday', [
                            'schedule_id' => $schedule->id,
                            'original_date' => $originalDate,
                            'adjusted_date' => $nextRun->format('Y-m-d'),
                        ]);
                    }

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
