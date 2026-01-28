<?php

namespace App\Console\Commands;

use App\Models\PayrollJob;
use App\Models\PayrollSchedule;
use App\Services\PayrollValidationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ValidatePayrollIntegrity extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payroll:validate-integrity 
                            {--fix : Automatically fix issues where possible}
                            {--schedule= : Validate specific schedule ID}
                            {--employee= : Validate specific employee ID}
                            {--limit=100 : Limit number of jobs to check}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Validate payroll data integrity and fix inconsistencies';

    public function __construct(
        protected PayrollValidationService $validationService
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $fix = $this->option('fix');
        $scheduleId = $this->option('schedule');
        $employeeId = $this->option('employee');
        $limit = (int) $this->option('limit');

        $this->info('Starting payroll integrity validation...');
        $this->newLine();

        $errors = 0;
        $warnings = 0;
        $fixed = 0;

        // Validate specific schedule
        if ($scheduleId) {
            $schedule = PayrollSchedule::find($scheduleId);
            if (! $schedule) {
                $this->error("Schedule #{$scheduleId} not found.");

                return Command::FAILURE;
            }

            $this->info("Validating schedule: {$schedule->name} (ID: {$schedule->id})");
            $result = $this->validateSchedule($schedule, $fix);
            $errors += $result['errors'];
            $warnings += $result['warnings'];
            $fixed += $result['fixed'];

            return $errors > 0 ? Command::FAILURE : Command::SUCCESS;
        }

        // Validate specific employee
        if ($employeeId) {
            $employee = \App\Models\Employee::find($employeeId);
            if (! $employee) {
                $this->error("Employee #{$employeeId} not found.");

                return Command::FAILURE;
            }

            $this->info("Validating employee: {$employee->name} (ID: {$employee->id})");
            $result = $this->validateEmployee($employee, $fix);
            $errors += $result['errors'];
            $warnings += $result['warnings'];
            $fixed += $result['fixed'];

            return $errors > 0 ? Command::FAILURE : Command::SUCCESS;
        }

        // Validate all schedules
        $this->info('Validating all payroll schedules...');
        $schedules = PayrollSchedule::with('employees')->get();

        $bar = $this->output->createProgressBar($schedules->count());
        $bar->start();

        foreach ($schedules as $schedule) {
            $result = $this->validateSchedule($schedule, $fix);
            $errors += $result['errors'];
            $warnings += $result['warnings'];
            $fixed += $result['fixed'];
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        // Validate payroll jobs
        $this->info("Validating payroll jobs (limit: {$limit})...");
        $jobsWithErrors = $this->validationService->findJobsWithErrors($limit);

        $bar = $this->output->createProgressBar($jobsWithErrors->count());
        $bar->start();

        foreach ($jobsWithErrors as $item) {
            $job = $item['job'];
            $validation = $item['validation'];

            foreach ($validation['errors'] as $error) {
                $this->newLine();
                $this->error("Job #{$job->id}: {$error}");
                $errors++;
            }

            foreach ($validation['warnings'] as $warning) {
                $this->newLine();
                $this->warn("Job #{$job->id}: {$warning}");
                $warnings++;
            }

            if ($fix && ! $validation['valid']) {
                // Attempt to fix issues
                $fixed += $this->fixJobIssues($job, $validation);
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        // Check for duplicates
        $this->info('Checking for duplicate payroll jobs...');
        $duplicates = $this->findDuplicates();

        if ($duplicates->isNotEmpty()) {
            $this->warn("Found {$duplicates->count()} duplicate payroll job(s)");
            foreach ($duplicates as $duplicate) {
                $this->line("  - Employee #{$duplicate->employee_id}, Period: {$duplicate->pay_period_start} to {$duplicate->pay_period_end}");
                $errors++;
            }

            if ($fix) {
                $fixed += $this->fixDuplicates($duplicates);
            }
        } else {
            $this->info('No duplicates found.');
        }

        $this->newLine();
        $this->info('Validation complete!');
        $this->table(
            ['Type', 'Count'],
            [
                ['Errors', $errors],
                ['Warnings', $warnings],
                ['Fixed', $fixed],
            ]
        );

        return $errors > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * Validate a schedule
     */
    protected function validateSchedule(PayrollSchedule $schedule, bool $fix): array
    {
        $result = $this->validationService->checkScheduleConsistency($schedule);
        $errors = 0;
        $warnings = 0;
        $fixed = 0;

        if (! $result['consistent']) {
            foreach ($result['issues'] as $issue) {
                $this->warn("Schedule #{$schedule->id}: {$issue}");
                $warnings++;
            }
        }

        // Validate all jobs for this schedule
        $jobs = $schedule->payrollJobs()->limit(100)->get();
        foreach ($jobs as $job) {
            $validation = $this->validationService->validatePayrollJob($job);
            if (! $validation['valid']) {
                $errors += count($validation['errors']);
                $warnings += count($validation['warnings']);

                if ($fix) {
                    $fixed += $this->fixJobIssues($job, $validation);
                }
            }
        }

        return compact('errors', 'warnings', 'fixed');
    }

    /**
     * Validate an employee
     */
    protected function validateEmployee(\App\Models\Employee $employee, bool $fix): array
    {
        $validations = $this->validationService->validateEmployeePayrollJobs($employee);
        $errors = 0;
        $warnings = 0;
        $fixed = 0;

        foreach ($validations as $validation) {
            if (! $validation['valid']) {
                foreach ($validation['errors'] as $error) {
                    $this->error("Job #{$validation['payroll_job_id']}: {$error}");
                    $errors++;
                }

                foreach ($validation['warnings'] as $warning) {
                    $this->warn("Job #{$validation['payroll_job_id']}: {$warning}");
                    $warnings++;
                }

                if ($fix) {
                    $job = PayrollJob::find($validation['payroll_job_id']);
                    if ($job) {
                        $fixed += $this->fixJobIssues($job, $validation);
                    }
                }
            }
        }

        // Check for duplicates
        $duplicateCheck = $this->validationService->checkForDuplicates(
            $employee,
            now()->startOfMonth(),
            now()->endOfMonth()
        );

        if ($duplicateCheck['has_duplicates']) {
            $this->warn("Found {$duplicateCheck['count']} duplicate job(s) for employee");
            $warnings++;
        }

        return compact('errors', 'warnings', 'fixed');
    }

    /**
     * Fix job issues where possible
     */
    protected function fixJobIssues(PayrollJob $job, array $validation): int
    {
        $fixed = 0;

        // Fix negative net salary
        if ($job->net_salary < 0) {
            $job->updateStatus('failed', 'Net salary was negative - corrected to 0');
            $job->update(['net_salary' => 0]);
            $this->info("  Fixed: Set negative net salary to 0 for job #{$job->id}");
            $fixed++;
        }

        // Fix missing period dates (shouldn't happen with NOT NULL constraint, but handle legacy data)
        if (! $job->pay_period_start || ! $job->pay_period_end) {
            if ($job->created_at) {
                $job->update([
                    'pay_period_start' => $job->created_at->startOfMonth(),
                    'pay_period_end' => $job->created_at->endOfMonth(),
                ]);
                $this->info("  Fixed: Set period dates from created_at for job #{$job->id}");
                $fixed++;
            }
        }

        return $fixed;
    }

    /**
     * Find duplicate payroll jobs
     */
    protected function findDuplicates(): \Illuminate\Support\Collection
    {
        return DB::table('payroll_jobs')
            ->select('employee_id', 'pay_period_start', 'pay_period_end', DB::raw('COUNT(*) as count'))
            ->whereIn('status', ['pending', 'processing', 'succeeded'])
            ->groupBy('employee_id', 'pay_period_start', 'pay_period_end')
            ->having('count', '>', 1)
            ->get()
            ->map(function ($row) {
                return PayrollJob::where('employee_id', $row->employee_id)
                    ->where('pay_period_start', $row->pay_period_start)
                    ->where('pay_period_end', $row->pay_period_end)
                    ->whereIn('status', ['pending', 'processing', 'succeeded'])
                    ->orderBy('created_at', 'desc')
                    ->get();
            })
            ->flatten();
    }

    /**
     * Fix duplicate payroll jobs (keep the first one, mark others as failed)
     */
    protected function fixDuplicates(\Illuminate\Support\Collection $duplicates): int
    {
        $fixed = 0;
        $grouped = $duplicates->groupBy(function ($job) {
            return "{$job->employee_id}_{$job->pay_period_start}_{$job->pay_period_end}";
        });

        foreach ($grouped as $group) {
            // Keep the first (oldest) job, mark others as failed
            $sorted = $group->sortBy('created_at');
            $first = $sorted->first();
            $others = $sorted->skip(1);

            foreach ($others as $job) {
                $job->updateStatus('failed', 'Duplicate payroll job - another job exists for this period');
                $this->info("  Fixed: Marked duplicate job #{$job->id} as failed");
                $fixed++;
            }
        }

        return $fixed;
    }
}
