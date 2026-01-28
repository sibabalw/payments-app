<?php

namespace App\Console\Commands;

use App\Models\PayrollJob;
use App\Services\AuditService;
use App\Services\LockService;
use App\Services\PayrollCalculationService;
use App\Services\PayrollValidationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RecalculatePayrollJob extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payroll:recalculate 
                            {job : Payroll job ID to recalculate}
                            {--create-new : Create a new corrected job instead of updating existing}
                            {--force : Force recalculation even if validation passes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recalculate a payroll job with current employee data and adjustments';

    public function __construct(
        protected PayrollCalculationService $calculationService,
        protected PayrollValidationService $validationService,
        protected LockService $lockService,
        protected AuditService $auditService
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $jobId = $this->argument('job');
        $createNew = $this->option('create-new');
        $force = $this->option('force');

        $payrollJob = PayrollJob::find($jobId);

        if (! $payrollJob) {
            $this->error("Payroll job #{$jobId} not found.");

            return Command::FAILURE;
        }

        // Use distributed lock to prevent concurrent recalculations of the same job
        $lockKey = "payroll_job_recalculate_{$jobId}";
        $lockAcquired = $this->lockService->acquire($lockKey, 10, 300); // 5 min TTL

        if (! $lockAcquired) {
            $this->error("Another recalculation process is already running for job #{$jobId}. Please wait.");

            return Command::FAILURE;
        }

        try {
            $this->info("Recalculating payroll job #{$jobId}");
            $this->newLine();

            // Prevent recalculation of succeeded or processing jobs unless creating new job
            if (in_array($payrollJob->status, ['succeeded', 'processing']) && ! $createNew) {
                $this->error("Cannot recalculate job with status '{$payrollJob->status}'. Use --create-new to create a corrected job instead.");
                $this->info('This protects historical calculation data integrity.');

                return Command::FAILURE;
            }

            // Validate current job
            $validation = $this->validationService->validatePayrollJob($payrollJob);

            if ($validation['valid'] && ! $force) {
                $this->info('Job validation passed. Use --force to recalculate anyway.');
                $this->displayValidation($validation);

                return Command::SUCCESS;
            }

            if (! $validation['valid']) {
                $this->warn('Job has validation errors:');
                $this->displayValidation($validation);
                $this->newLine();
            }

            // Get employee
            $employee = $payrollJob->employee;
            if (! $employee) {
                $this->error('Employee not found for this payroll job.');

                return Command::FAILURE;
            }

            // Recalculate
            $this->info('Recalculating with current employee data and adjustments...');

            try {
                $calculation = $this->calculationService->calculatePayroll(
                    $employee,
                    $payrollJob->pay_period_start,
                    $payrollJob->pay_period_end
                );

                $this->displayCalculationComparison($payrollJob, $calculation);

                // Audit log the recalculation attempt
                $this->auditService->log(
                    'payroll.recalculated',
                    $payrollJob,
                    [
                        'payroll_job_id' => $payrollJob->id,
                        'employee_id' => $payrollJob->employee_id,
                        'old_gross_salary' => $payrollJob->gross_salary,
                        'new_gross_salary' => $calculation['gross_salary'],
                        'old_net_salary' => $payrollJob->net_salary,
                        'new_net_salary' => $calculation['net_salary'],
                        'create_new' => $createNew,
                        'force' => $force,
                    ]
                );

                if ($createNew) {
                    return $this->createNewJob($payrollJob, $calculation);
                }

                // Ask for confirmation before updating
                if (! $this->confirm('Update existing job with recalculated values?', true)) {
                    $this->info('Recalculation cancelled.');

                    return Command::SUCCESS;
                }

                return $this->updateExistingJob($payrollJob, $calculation);
            } catch (\Exception $e) {
                $this->error("Recalculation failed: {$e->getMessage()}");
                $this->error($e->getTraceAsString());

                return Command::FAILURE;
            }
        } finally {
            // Always release the lock
            $this->lockService->release($lockKey);
        }
    }

    /**
     * Display validation results
     */
    protected function displayValidation(array $validation): void
    {
        if (! empty($validation['errors'])) {
            $this->error('Errors:');
            foreach ($validation['errors'] as $error) {
                $this->line("  - {$error}");
            }
        }

        if (! empty($validation['warnings'])) {
            $this->warn('Warnings:');
            foreach ($validation['warnings'] as $warning) {
                $this->line("  - {$warning}");
            }
        }
    }

    /**
     * Display calculation comparison
     */
    protected function displayCalculationComparison(PayrollJob $job, array $calculation): void
    {
        $this->newLine();
        $this->info('Calculation Comparison:');
        $this->table(
            ['Field', 'Current', 'Recalculated', 'Difference'],
            [
                [
                    'Gross Salary',
                    number_format($job->gross_salary, 2),
                    number_format($calculation['gross_salary'], 2),
                    number_format($calculation['gross_salary'] - $job->gross_salary, 2),
                ],
                [
                    'PAYE',
                    number_format($job->paye_amount, 2),
                    number_format($calculation['paye_amount'], 2),
                    number_format($calculation['paye_amount'] - $job->paye_amount, 2),
                ],
                [
                    'UIF',
                    number_format($job->uif_amount, 2),
                    number_format($calculation['uif_amount'], 2),
                    number_format($calculation['uif_amount'] - $job->uif_amount, 2),
                ],
                [
                    'Net Salary',
                    number_format($job->net_salary, 2),
                    number_format($calculation['net_salary'], 2),
                    number_format($calculation['net_salary'] - $job->net_salary, 2),
                ],
            ]
        );
    }

    /**
     * Update existing job with recalculated values
     * Only allowed for pending or failed jobs to protect data integrity
     */
    protected function updateExistingJob(PayrollJob $job, array $calculation): int
    {
        // Double-check status before updating (defense in depth)
        if (! in_array($job->status, ['pending', 'failed'])) {
            $this->error("Cannot update job with status '{$job->status}'. Only pending or failed jobs can be recalculated.");

            return Command::FAILURE;
        }

        // Note: We can't update immutable fields directly via model, so we use DB::table
        // This is a special case for recalculation of pending/failed jobs only
        try {
            DB::table('payroll_jobs')
                ->where('id', $job->id)
                ->whereIn('status', ['pending', 'failed']) // Additional safety check at DB level
                ->update([
                    'gross_salary' => $calculation['gross_salary'],
                    'paye_amount' => $calculation['paye_amount'],
                    'uif_amount' => $calculation['uif_amount'],
                    'sdl_amount' => $calculation['sdl_amount'],
                    'adjustments' => json_encode($calculation['adjustments']),
                    'net_salary' => $calculation['net_salary'],
                    'calculation_hash' => $calculation['calculation_hash'],
                    'calculation_snapshot' => json_encode($calculation['calculation_snapshot']),
                    'employee_snapshot' => json_encode($calculation['employee_snapshot']),
                    'updated_at' => now(),
                ]);

            $this->info("Successfully updated payroll job #{$job->id} with recalculated values.");

            // Re-validate
            $job->refresh();
            $validation = $this->validationService->validatePayrollJob($job);

            if ($validation['valid']) {
                $this->info('Job validation now passes.');
            } else {
                $this->warn('Job still has validation issues:');
                $this->displayValidation($validation);
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Failed to update job: {$e->getMessage()}");

            return Command::FAILURE;
        }
    }

    /**
     * Create new corrected job
     */
    protected function createNewJob(PayrollJob $oldJob, array $calculation): int
    {
        $this->info('Creating new corrected payroll job...');

        try {
            $newJob = $oldJob->payrollSchedule->payrollJobs()->create([
                'employee_id' => $oldJob->employee_id,
                'gross_salary' => $calculation['gross_salary'],
                'paye_amount' => $calculation['paye_amount'],
                'uif_amount' => $calculation['uif_amount'],
                'sdl_amount' => $calculation['sdl_amount'],
                'adjustments' => $calculation['adjustments'],
                'net_salary' => $calculation['net_salary'],
                'currency' => $oldJob->currency,
                'status' => 'pending',
                'pay_period_start' => $oldJob->pay_period_start,
                'pay_period_end' => $oldJob->pay_period_end,
                'calculation_hash' => $calculation['calculation_hash'],
                'calculation_snapshot' => $calculation['calculation_snapshot'],
                'employee_snapshot' => $calculation['employee_snapshot'],
            ]);

            // Mark old job as recalculated (if status allows)
            if (in_array($oldJob->status, ['pending', 'failed'])) {
                $oldJob->updateStatus('failed', "Recalculated - replaced by job #{$newJob->id}");
            }

            $this->info("Created new payroll job #{$newJob->id}");
            $this->info("Old job #{$oldJob->id} marked as recalculated");

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Failed to create new job: {$e->getMessage()}");

            return Command::FAILURE;
        }
    }
}
