<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\PayrollJob;
use App\Models\PayrollSchedule;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class PayrollValidationService
{
    public function __construct(
        protected PayrollCalculationService $calculationService
    ) {}

    /**
     * Validate a payroll job's calculation correctness
     *
     * @return array Validation result with errors and warnings
     */
    public function validatePayrollJob(PayrollJob $payrollJob): array
    {
        $result = $this->calculationService->validateCalculation($payrollJob);
        $errors = $result['errors'] ?? [];
        $warnings = $result['warnings'] ?? [];

        // Additional validations
        $employee = $payrollJob->employee;
        if (! $employee) {
            $errors[] = 'Employee not found';
        } else {
            // Validate period dates
            if (! $payrollJob->pay_period_start || ! $payrollJob->pay_period_end) {
                $errors[] = 'Pay period dates are missing';
            } else {
                if ($payrollJob->pay_period_start->gt($payrollJob->pay_period_end)) {
                    $errors[] = 'Pay period start date is after end date';
                }
            }

            // Validate schedule relationship
            if (! $payrollJob->payrollSchedule) {
                $errors[] = 'Payroll schedule not found';
            } else {
                // Check if employee is in the schedule
                $employeeInSchedule = $payrollJob->payrollSchedule->employees()
                    ->where('employees.id', $employee->id)
                    ->exists();

                if (! $employeeInSchedule) {
                    $warnings[] = 'Employee is not assigned to this payroll schedule';
                }
            }

            // Validate amounts are non-negative (except net can be 0)
            if ($payrollJob->gross_salary < 0) {
                $errors[] = 'Gross salary cannot be negative';
            }
            if ($payrollJob->paye_amount < 0) {
                $errors[] = 'PAYE amount cannot be negative';
            }
            if ($payrollJob->uif_amount < 0) {
                $errors[] = 'UIF amount cannot be negative';
            }
            if ($payrollJob->net_salary < 0) {
                $errors[] = 'Net salary cannot be negative';
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
            'payroll_job_id' => $payrollJob->id,
        ];
    }

    /**
     * Check for duplicate payroll jobs for an employee in a period
     *
     * @return array Duplicate check result
     */
    public function checkForDuplicates(Employee $employee, Carbon $periodStart, Carbon $periodEnd): array
    {
        $duplicates = PayrollJob::where('employee_id', $employee->id)
            ->where('pay_period_start', $periodStart->format('Y-m-d'))
            ->where('pay_period_end', $periodEnd->format('Y-m-d'))
            ->whereIn('status', ['pending', 'processing', 'succeeded'])
            ->get();

        return [
            'has_duplicates' => $duplicates->count() > 1,
            'count' => $duplicates->count(),
            'jobs' => $duplicates->map(fn ($job) => [
                'id' => $job->id,
                'status' => $job->status,
                'schedule_id' => $job->payroll_schedule_id,
                'created_at' => $job->created_at,
            ]),
        ];
    }

    /**
     * Check schedule consistency
     *
     * @return array Consistency check result
     */
    public function checkScheduleConsistency(PayrollSchedule $schedule): array
    {
        $issues = [];

        // Check if schedule has employees
        if ($schedule->employees->isEmpty()) {
            $issues[] = 'Schedule has no employees assigned';
        }

        // Check for employees in multiple recurring schedules
        if ($schedule->isRecurring()) {
            foreach ($schedule->employees as $employee) {
                $otherRecurringSchedules = $employee->payrollSchedules()
                    ->where('schedule_type', 'recurring')
                    ->where('status', 'active')
                    ->where('id', '!=', $schedule->id)
                    ->count();

                if ($otherRecurringSchedules > 0) {
                    $issues[] = "Employee {$employee->name} (ID: {$employee->id}) is in {$otherRecurringSchedules} other recurring schedule(s)";
                }
            }
        }

        // Check if next_run_at is in the past for active schedules
        if ($schedule->status === 'active' && $schedule->next_run_at && $schedule->next_run_at->isPast()) {
            $issues[] = 'Schedule next_run_at is in the past';
        }

        // Check for orphaned payroll jobs (jobs without valid schedule)
        $orphanedJobs = $schedule->payrollJobs()
            ->whereDoesntHave('payrollSchedule')
            ->count();

        if ($orphanedJobs > 0) {
            $issues[] = "Schedule has {$orphanedJobs} orphaned payroll job(s)";
        }

        return [
            'consistent' => empty($issues),
            'issues' => $issues,
            'schedule_id' => $schedule->id,
        ];
    }

    /**
     * Validate all payroll jobs for an employee
     *
     * @return Collection Collection of validation results
     */
    public function validateEmployeePayrollJobs(Employee $employee): Collection
    {
        $jobs = $employee->payrollJobs()
            ->orderBy('pay_period_start', 'desc')
            ->get();

        return $jobs->map(fn ($job) => $this->validatePayrollJob($job));
    }

    /**
     * Find payroll jobs with calculation errors
     *
     * @return Collection Collection of payroll jobs with errors
     */
    public function findJobsWithErrors(?int $limit = 100): Collection
    {
        $jobs = PayrollJob::whereIn('status', ['pending', 'processing', 'succeeded'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        return $jobs->filter(function ($job) {
            $validation = $this->validatePayrollJob($job);

            return ! $validation['valid'];
        })->map(function ($job) {
            $validation = $this->validatePayrollJob($job);

            return [
                'job' => $job,
                'validation' => $validation,
            ];
        });
    }

    /**
     * Validate period overlap
     * Check if a period overlaps with existing payroll jobs
     *
     * @return array Overlap check result
     */
    public function checkPeriodOverlap(Employee $employee, Carbon $periodStart, Carbon $periodEnd): array
    {
        $overlapping = PayrollJob::where('employee_id', $employee->id)
            ->whereIn('status', ['pending', 'processing', 'succeeded'])
            ->where(function ($query) use ($periodStart, $periodEnd) {
                $query->where(function ($q) use ($periodStart) {
                    // Period starts within existing period
                    $q->where('pay_period_start', '<=', $periodStart)
                        ->where('pay_period_end', '>=', $periodStart);
                })->orWhere(function ($q) use ($periodEnd) {
                    // Period ends within existing period
                    $q->where('pay_period_start', '<=', $periodEnd)
                        ->where('pay_period_end', '>=', $periodEnd);
                })->orWhere(function ($q) use ($periodStart, $periodEnd) {
                    // Period completely contains existing period
                    $q->where('pay_period_start', '>=', $periodStart)
                        ->where('pay_period_end', '<=', $periodEnd);
                });
            })
            ->get();

        return [
            'has_overlap' => $overlapping->isNotEmpty(),
            'overlapping_jobs' => $overlapping->map(fn ($job) => [
                'id' => $job->id,
                'period_start' => $job->pay_period_start->format('Y-m-d'),
                'period_end' => $job->pay_period_end->format('Y-m-d'),
                'status' => $job->status,
            ]),
        ];
    }
}
