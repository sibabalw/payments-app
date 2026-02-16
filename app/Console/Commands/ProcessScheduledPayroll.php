<?php

namespace App\Console\Commands;

use App\Jobs\BatchProcessPayrollJob;
use App\Jobs\ProcessPayrollJob;
use App\Models\PayrollJob;
use App\Models\PayrollSchedule;
use App\Services\AdjustmentService;
use App\Services\BatchProcessingService;
use App\Services\CronExpressionService;
use App\Services\EscrowService;
use App\Services\LockService;
use App\Services\PayrollCalculationService;
use App\Services\PayrollValidationService;
use App\Services\SalaryCalculationService;
use App\Services\SettlementService;
use App\Services\SouthAfricaHolidayService;
use App\Services\SouthAfricanTaxService;
use App\Traits\RetriesTransactions;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessScheduledPayroll extends Command
{
    use RetriesTransactions;

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
        protected SouthAfricaHolidayService $holidayService,
        protected CronExpressionService $cronService,
        protected PayrollCalculationService $payrollCalculationService,
        protected PayrollValidationService $validationService,
        protected LockService $lockService,
        protected BatchProcessingService $batchProcessingService,
        protected SettlementService $settlementService,
        protected EscrowService $escrowService
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            return $this->runHandle();
        } catch (\Throwable $e) {
            Log::error('payroll:process-scheduled command failed', [
                'message' => $e->getMessage(),
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Run the main command logic (wrapped so we can log any exception before rethrowing).
     */
    protected function runHandle(): int
    {
        // Generate operation ID for schedule execution tracking
        $operationId = \App\Helpers\LogContext::generateOperationId();
        $correlationId = \Illuminate\Support\Str::uuid()->toString();

        // Log operation start
        $logContext = \App\Helpers\LogContext::logOperationStart('process_scheduled_payroll', \App\Helpers\LogContext::create(
            $correlationId,
            null,
            null,
            'schedule_execution',
            null,
            ['operation_id' => $operationId]
        ));

        // Process schedules in chunks to avoid memory issues with large datasets
        $totalJobs = 0;
        $processedSchedules = 0;

        // Optimized query - only select needed columns and use chunk for memory efficiency
        PayrollSchedule::due()
            ->select(['id', 'business_id', 'name', 'schedule_type', 'frequency', 'next_run_at', 'last_run_at', 'status'])
            ->with([
                'business:id,status,escrow_balance', // Only load necessary business fields
            ])
            ->chunk(50, function ($dueSchedules) use (&$totalJobs, &$processedSchedules) {
                foreach ($dueSchedules as $schedule) {
                    // Use distributed lock to prevent concurrent execution of same schedule
                    // Reduced TTL to match expected processing time (max 10 minutes for large schedules)
                    $lockKey = "payroll_schedule_{$schedule->id}_execution";
                    $lockTTL = (int) config('payroll.schedule_lock_ttl', 600); // Default 10 minutes
                    $heartbeatInterval = (int) config('payroll.schedule_lock_heartbeat_interval', 300); // Default 5 minutes

                    $lockAcquired = $this->lockService->acquire($lockKey, 10, $lockTTL);

                    if (! $lockAcquired) {
                        $this->warn("Schedule #{$schedule->id} is already being processed by another instance. Skipping.");
                        Log::info('Payroll schedule execution skipped - lock already held', [
                            'schedule_id' => $schedule->id,
                        ]);

                        continue;
                    }

                    try {
                        // Start heartbeat timer for long-running operations
                        $heartbeatTimer = $this->startLockHeartbeat($lockKey, $lockTTL, $heartbeatInterval);

                        try {
                            $scheduleJobs = $this->processSchedule($schedule);
                            $totalJobs += $scheduleJobs;
                            $processedSchedules++;
                        } finally {
                            // Stop heartbeat timer
                            if ($heartbeatTimer) {
                                $heartbeatTimer->stop();
                            }
                        }
                    } finally {
                        // Always release the lock
                        $this->lockService->release($lockKey);
                    }
                }
            });

        if ($processedSchedules === 0) {
            $this->info('No due payroll schedules found.');
            \App\Helpers\LogContext::logOperationEnd('process_scheduled_payroll', array_merge($logContext, [
                'correlation_id' => $correlationId,
                'operation_id' => $operationId,
            ]), true, [
                'schedules_processed' => 0,
                'jobs_dispatched' => 0,
            ]);

            return Command::SUCCESS;
        }

        $this->info("Processed {$processedSchedules} schedule(s) and dispatched {$totalJobs} payroll job(s) to the queue.");

        // Log operation end
        \App\Helpers\LogContext::logOperationEnd('process_scheduled_payroll', array_merge($logContext, [
            'correlation_id' => $correlationId,
            'operation_id' => $operationId,
        ]), true, [
            'schedules_processed' => $processedSchedules,
            'jobs_dispatched' => $totalJobs,
        ]);

        return Command::SUCCESS;
    }

    /**
     * Process a single payroll schedule with transaction protection
     *
     * @return int Number of jobs created
     */
    protected function processSchedule(PayrollSchedule $schedule): int
    {
        // Skip if business is banned or suspended - check status directly for efficiency
        // Business is already eager loaded, so direct status check is faster than method call
        $business = $schedule->business;
        if ($business && $business->status !== 'active') {
            $this->warn("Schedule #{$schedule->id} belongs to a {$business->status} business. Skipping.");

            return 0;
        }

        // Calculate pay period dates based on schedule type FIRST
        // This must be done before eager loading employees to use in closures
        $calculatedPeriod = $schedule->calculatePayPeriod();
        $payPeriodStart = $calculatedPeriod['start'];
        $payPeriodEnd = $calculatedPeriod['end'];

        // Reload employees with only necessary relationships for calculation
        // Use cursor() for large employee sets to reduce memory usage
        // This ensures we have fresh data within the transaction while minimizing memory usage
        $employeeQuery = $schedule->employees()
            ->select([
                'employees.id',
                'employees.business_id',
                'employees.name',
                'employees.email',
                'employees.id_number',
                'employees.tax_number',
                'employees.employment_type',
                'employees.gross_salary',
                'employees.hourly_rate',
                'employees.hours_worked_per_month',
                'employees.overtime_rate_multiplier',
                'employees.weekend_rate_multiplier',
                'employees.holiday_rate_multiplier',
                'employees.tax_status',
            ])
            ->with([
                'adjustments' => function ($query) {
                    // Only load active adjustments that might be used in calculations
                    $query->where('is_active', true)
                        ->select([
                            'adjustments.id',
                            'adjustments.employee_id',
                            'adjustments.type',
                            'adjustments.amount',
                            'adjustments.adjustment_type',
                            'adjustments.period_start',
                            'adjustments.period_end',
                        ]);
                },
                'timeEntries' => function ($query) use ($payPeriodStart, $payPeriodEnd) {
                    // Load time entries for the period to calculate hourly wages
                    $query->whereBetween('date', [$payPeriodStart, $payPeriodEnd])
                        ->whereNotNull('sign_out_time')
                        ->select(['time_entries.id', 'time_entries.employee_id', 'time_entries.date', 'time_entries.sign_in_time', 'time_entries.sign_out_time']);
                },
                'leaveEntries' => function ($query) use ($payPeriodStart, $payPeriodEnd) {
                    // Load leave entries for the period
                    $query->whereBetween('start_date', [$payPeriodStart, $payPeriodEnd])
                        ->orWhereBetween('end_date', [$payPeriodStart, $payPeriodEnd])
                        ->select(['leave_entries.id', 'leave_entries.employee_id', 'leave_entries.start_date', 'leave_entries.end_date', 'leave_entries.leave_type']);
                },
            ]);

        // Use cursor() for large datasets (>100 employees) to reduce memory usage
        $employeeCount = $employeeQuery->count();
        if ($employeeCount > 100) {
            $employees = collect();
            $employeeQuery->cursor()->each(function ($employee) use (&$employees) {
                $employees->push($employee);
            });
        } else {
            $employees = $employeeQuery->get();
        }

        Log::info('Calculated pay period for schedule', [
            'schedule_id' => $schedule->id,
            'schedule_name' => $schedule->name,
            'schedule_type' => $schedule->schedule_type,
            'period_start' => $payPeriodStart->format('Y-m-d'),
            'period_end' => $payPeriodEnd->format('Y-m-d'),
        ]);

        // Wrap entire schedule processing in transaction with retry logic for lock timeouts
        // Use retry logic to handle potential lock timeouts under high load
        $maxRetries = (int) config('payroll.transaction_max_retries', 3);
        $retryDelay = (int) config('payroll.transaction_retry_delay', 1);

        return $this->retryTransaction(function () use ($schedule, $payPeriodStart, $payPeriodEnd, $employees) {
            $lockedSchedule = PayrollSchedule::where('id', $schedule->id)
                ->lockForUpdate()
                ->first();

            if (! $lockedSchedule) {
                Log::warning('Payroll schedule not found', [
                    'schedule_id' => $schedule->id,
                ]);

                return 0;
            }

            // Validate we can process BEFORE claiming: do not update last_run_at/next_run_at unless we will create jobs.
            // Otherwise the schedule appears "run" but no payroll jobs were created or dispatched.
            if ($employees->isEmpty()) {
                Log::warning('Payroll schedule has no employees assigned - skipping (schedule stays due)', [
                    'schedule_id' => $lockedSchedule->id,
                    'schedule_name' => $lockedSchedule->name,
                    'business_id' => $lockedSchedule->business_id,
                ]);

                $this->warn("Schedule #{$lockedSchedule->id} has no employees assigned. Skipping (schedule stays due).");

                return 0;
            }

            $business = \App\Models\Business::where('id', $lockedSchedule->business_id)
                ->lockForUpdate()
                ->first();

            if (! $business) {
                Log::warning('Business not found for payroll schedule', [
                    'schedule_id' => $lockedSchedule->id,
                    'business_id' => $lockedSchedule->business_id,
                ]);

                return 0;
            }

            if ($business->escrow_balance === null) {
                Log::warning('Payroll schedule skipped - escrow balance is NULL (schedule stays due)', [
                    'schedule_id' => $lockedSchedule->id,
                    'business_id' => $business->id,
                ]);

                $this->warn("Skipping schedule #{$lockedSchedule->id} - escrow balance is NULL. Schedule stays due.");

                return 0;
            }

            $escrowBalance = $this->escrowService->getAvailableBalance($business, false, false);

            if ($escrowBalance === 0) {
                Log::warning('Payroll schedule skipped - escrow balance is zero (schedule stays due)', [
                    'schedule_id' => $lockedSchedule->id,
                    'business_id' => $business->id,
                ]);

                $this->warn("Skipping schedule #{$lockedSchedule->id} - escrow balance is zero. Schedule stays due.");

                return 0;
            }

            if ($escrowBalance < 0) {
                Log::warning('Payroll schedule skipped - escrow balance is negative (schedule stays due)', [
                    'schedule_id' => $lockedSchedule->id,
                    'business_id' => $business->id,
                    'escrow_balance' => $escrowBalance,
                ]);

                $this->warn("Skipping schedule #{$lockedSchedule->id} - escrow balance is negative. Schedule stays due.");

                return 0;
            }

            // All pre-checks passed: now claim the schedule (update last_run_at and next_run_at) so we only mark as run when we will create jobs.
            $updateData = [
                'last_run_at' => now(),
                'updated_at' => now(),
            ];

            if ($lockedSchedule->isOneTime()) {
                $updateData['next_run_at'] = null;
                $updateData['status'] = 'cancelled';
            } else {
                try {
                    // Use the schedule's due time (next_run_at) as base so next run is the exact
                    // payroll date next period (e.g. 6th at 09:00 → next month 6th at 09:00), not
                    // "next occurrence from now" which can drift when runs are late or employees skipped.
                    $fromDate = $lockedSchedule->next_run_at
                        ? \Carbon\Carbon::parse($lockedSchedule->next_run_at)->setTimezone(config('app.timezone'))
                        : now(config('app.timezone'));
                    $nextRun = $this->cronService->getNextRunDate($lockedSchedule->frequency, $fromDate);

                    if (! $this->holidayService->isBusinessDay($nextRun)) {
                        $originalTime = $nextRun->format('H:i');
                        $nextRun = $this->holidayService->getNextBusinessDay($nextRun);
                        $nextRun->setTime((int) explode(':', $originalTime)[0], (int) explode(':', $originalTime)[1]);
                    }

                    $updateData['next_run_at'] = $nextRun;
                } catch (\Throwable $e) {
                    Log::error('Failed to calculate next run time during payroll schedule claim', [
                        'schedule_id' => $lockedSchedule->id,
                        'frequency' => $lockedSchedule->frequency,
                        'error' => $e->getMessage(),
                    ]);
                    throw new \RuntimeException(
                        'Unable to compute next run date for payroll schedule #'.$lockedSchedule->id.': '.$e->getMessage(),
                        0,
                        $e
                    );
                }
            }

            $whereCondition = $lockedSchedule->isOneTime()
                ? function ($query) {
                    $query->whereNull('last_run_at');
                }
            : function ($query) {
                $query->whereNull('last_run_at')
                    ->orWhereColumn('last_run_at', '<', 'next_run_at');
            };

            $updated = PayrollSchedule::where('id', $schedule->id)
                ->where($whereCondition)
                ->update($updateData);

            if ($updated === 0) {
                Log::info('Payroll schedule already processed by another process', [
                    'schedule_id' => $schedule->id,
                    'last_run_at' => $lockedSchedule->last_run_at,
                    'next_run_at' => $lockedSchedule->next_run_at,
                ]);

                return 0;
            }

            $lockedSchedule->refresh();

            // Generate schedule run ID for audit trail (all jobs from this run share the same ID)
            $scheduleRunId = \Illuminate\Support\Str::uuid()->toString();

            $totalJobs = 0;
            $jobsToCreate = [];
            // Adaptive batch sizing based on system load, memory, and queue depth
            $batchSize = $this->calculateAdaptiveBatchSize();

            // Pre-process employees: validate and calculate in batches
            $employees->chunk($batchSize)->each(function ($employeeBatch) use (
                $payPeriodStart,
                $payPeriodEnd,
                $lockedSchedule,
                $scheduleRunId,
                &$jobsToCreate,
                &$totalJobs
            ) {
                foreach ($employeeBatch as $employee) {
                    try {
                        // Check for period overlaps before creating job
                        $overlapCheck = $this->validationService->checkPeriodOverlap(
                            $employee,
                            $payPeriodStart,
                            $payPeriodEnd
                        );

                        if ($overlapCheck['has_overlap']) {
                            $overlappingPeriods = collect($overlapCheck['overlapping_jobs'])
                                ->map(fn ($job) => "{$job['period_start']} to {$job['period_end']} ({$job['status']})")
                                ->join(', ');

                            Log::warning('Payroll job skipped due to period overlap', [
                                'employee_id' => $employee->id,
                                'employee_name' => $employee->name,
                                'schedule_id' => $lockedSchedule->id,
                                'period_start' => $payPeriodStart->format('Y-m-d'),
                                'period_end' => $payPeriodEnd->format('Y-m-d'),
                                'overlapping_jobs' => $overlapCheck['overlapping_jobs'],
                            ]);

                            $this->warn("Skipping {$employee->name} - pay period overlaps with existing jobs: {$overlappingPeriods}");

                            continue;
                        }

                        // Use PayrollCalculationService for all calculations
                        $calculation = $this->payrollCalculationService->calculatePayroll(
                            $employee,
                            $payPeriodStart,
                            $payPeriodEnd
                        );

                        // Prepare job data for batch insert
                        $jobsToCreate[] = [
                            'payroll_schedule_id' => $lockedSchedule->id,
                            'schedule_run_id' => $scheduleRunId,
                            'employee_id' => $employee->id,
                            'gross_salary' => $calculation['gross_salary'],
                            'paye_amount' => $calculation['paye_amount'],
                            'uif_amount' => $calculation['uif_amount'],
                            'sdl_amount' => $calculation['sdl_amount'],
                            'adjustments' => json_encode($calculation['adjustments']),
                            'net_salary' => $calculation['net_salary'],
                            'currency' => 'ZAR',
                            'status' => 'pending',
                            'pay_period_start' => $payPeriodStart->format('Y-m-d'),
                            'pay_period_end' => $payPeriodEnd->format('Y-m-d'),
                            'calculation_hash' => $calculation['calculation_hash'],
                            'calculation_version' => $calculation['calculation_version'] ?? 1,
                            'adjustment_inputs' => json_encode($calculation['adjustment_inputs'] ?? []),
                            'calculation_snapshot' => json_encode($calculation['calculation_snapshot']),
                            'employee_snapshot' => json_encode($calculation['employee_snapshot']),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    } catch (\Exception $e) {
                        Log::error('Failed to prepare payroll job for employee', [
                            'employee_id' => $employee->id,
                            'employee_name' => $employee->name,
                            'schedule_id' => $lockedSchedule->id,
                            'error' => $e->getMessage(),
                        ]);

                        $this->error("Failed to prepare payroll job for {$employee->name}: {$e->getMessage()}");
                    }
                }
            });

            // Check escrow balance again after calculating all payroll amounts
            // This ensures we don't create jobs if total exceeds available balance
            // Use net_salary as that's what's actually paid out to employees
            if (! empty($jobsToCreate)) {
                // Refresh business to get latest balance (still within transaction with lock)
                $business->refresh();

                // CRITICAL CHECK: escrow_balance must be NOT NULL (re-check after refresh)
                if ($business->escrow_balance === null) {
                    Log::warning('Payroll schedule skipped - escrow balance is NULL after calculation', [
                        'schedule_id' => $lockedSchedule->id,
                        'business_id' => $business->id,
                        'escrow_balance' => null,
                    ]);

                    $this->warn("Skipping schedule #{$lockedSchedule->id} - escrow balance is NULL after calculation");

                    return 0;
                }

                // Calculate total net salary (what's actually paid out)
                $totalPayrollAmount = array_sum(array_column($jobsToCreate, 'net_salary'));

                // Get available balance with locked row (no refresh needed since we just refreshed it)
                $currentBalance = $this->escrowService->getAvailableBalance($business, false, false);

                // CRITICAL CHECK: escrow balance must be greater than zero (explicit zero check)
                if ($currentBalance === 0) {
                    Log::warning('Payroll schedule skipped - escrow balance is exactly zero after calculation', [
                        'schedule_id' => $lockedSchedule->id,
                        'business_id' => $business->id,
                        'escrow_balance' => $currentBalance,
                        'total_payroll_amount' => $totalPayrollAmount,
                        'total_net_salary' => $totalPayrollAmount,
                    ]);

                    $this->warn("Skipping schedule #{$lockedSchedule->id} - escrow balance is zero");

                    return 0;
                }

                if ($currentBalance < 0) {
                    Log::warning('Payroll schedule skipped - escrow balance is negative after calculation', [
                        'schedule_id' => $lockedSchedule->id,
                        'business_id' => $business->id,
                        'escrow_balance' => $currentBalance,
                        'total_payroll_amount' => $totalPayrollAmount,
                        'total_net_salary' => $totalPayrollAmount,
                    ]);

                    $this->warn("Skipping schedule #{$lockedSchedule->id} - escrow balance is negative (balance: {$currentBalance})");

                    return 0;
                }

                // Explicit check: available balance must be sufficient for total net salary required
                if ($currentBalance < $totalPayrollAmount) {
                    Log::warning('Payroll schedule skipped - insufficient escrow balance', [
                        'schedule_id' => $lockedSchedule->id,
                        'business_id' => $business->id,
                        'escrow_balance' => $currentBalance,
                        'required_amount' => $totalPayrollAmount,
                        'shortfall' => $totalPayrollAmount - $currentBalance,
                    ]);

                    $this->warn("Skipping schedule #{$lockedSchedule->id} - insufficient escrow balance. Available: ".number_format($currentBalance, 2).', Required: '.number_format($totalPayrollAmount, 2));

                    return 0;
                }
            }

            // Bulk insert payroll jobs
            if (! empty($jobsToCreate)) {
                try {
                    // CRITICAL: Calculate total amount for entire batch before any inserts
                    // This prevents creating jobs when total batch exceeds balance
                    $totalBatchAmount = array_sum(array_column($jobsToCreate, 'net_salary'));

                    // Explicit check: total batch amount must not exceed available balance
                    if ($currentBalance < $totalBatchAmount) {
                        Log::warning('Payroll schedule skipped - total batch amount exceeds available balance', [
                            'schedule_id' => $lockedSchedule->id,
                            'business_id' => $business->id,
                            'escrow_balance' => $currentBalance,
                            'total_batch_amount' => $totalBatchAmount,
                            'shortfall' => $totalBatchAmount - $currentBalance,
                            'job_count' => count($jobsToCreate),
                        ]);

                        $this->warn("Skipping schedule #{$lockedSchedule->id} - total batch amount exceeds available balance. Available: ".number_format($currentBalance, 2).', Total batch: '.number_format($totalBatchAmount, 2));

                        return 0;
                    }

                    // Use batch insert for better performance
                    // Larger chunks (200) for better performance while staying within query limits
                    $chunks = array_chunk($jobsToCreate, max($batchSize, 200));

                    foreach ($chunks as $chunk) {
                        // CRITICAL: Re-check balance before each chunk insert with locked business row
                        // This prevents race conditions where balance might have changed between initial check and insert
                        $business->refresh();
                        $chunkTotalNetSalary = array_sum(array_column($chunk, 'net_salary'));

                        // CRITICAL CHECK: escrow_balance must be NOT NULL (re-check after refresh)
                        if ($business->escrow_balance === null) {
                            Log::warning('Payroll job chunk skipped - escrow balance is NULL after refresh', [
                                'schedule_id' => $lockedSchedule->id,
                                'business_id' => $business->id,
                                'escrow_balance' => null,
                                'chunk_total_net_salary' => $chunkTotalNetSalary,
                                'chunk_size' => count($chunk),
                            ]);

                            $this->warn('Skipping chunk of '.count($chunk).' payroll jobs - escrow balance is NULL');

                            continue;
                        }

                        $currentBalance = $this->escrowService->getAvailableBalance($business, false, false);

                        // CRITICAL CHECK: escrow balance must be greater than zero (explicit zero check)
                        if ($currentBalance === 0) {
                            Log::warning('Payroll job chunk skipped - escrow balance is exactly zero', [
                                'schedule_id' => $lockedSchedule->id,
                                'business_id' => $business->id,
                                'escrow_balance' => $currentBalance,
                                'chunk_total_net_salary' => $chunkTotalNetSalary,
                                'chunk_size' => count($chunk),
                            ]);

                            $this->warn('Skipping chunk of '.count($chunk).' payroll jobs - escrow balance is zero');

                            continue;
                        }

                        if ($currentBalance < 0) {
                            Log::warning('Payroll job chunk skipped - escrow balance is negative', [
                                'schedule_id' => $lockedSchedule->id,
                                'business_id' => $business->id,
                                'escrow_balance' => $currentBalance,
                                'chunk_total_net_salary' => $chunkTotalNetSalary,
                                'chunk_size' => count($chunk),
                            ]);

                            $this->warn('Skipping chunk of '.count($chunk)." payroll jobs - escrow balance is negative (balance: {$currentBalance})");

                            continue;
                        }

                        // Explicit check: available balance must be sufficient for chunk total net salary
                        if ($currentBalance < $chunkTotalNetSalary) {
                            Log::warning('Payroll job chunk skipped - insufficient escrow balance', [
                                'schedule_id' => $lockedSchedule->id,
                                'business_id' => $business->id,
                                'escrow_balance' => $currentBalance,
                                'chunk_total_net_salary' => $chunkTotalNetSalary,
                                'shortfall' => $chunkTotalNetSalary - $currentBalance,
                                'chunk_size' => count($chunk),
                            ]);

                            $this->warn('Skipping chunk of '.count($chunk).' payroll jobs - insufficient escrow balance. Available: '.number_format($currentBalance, 2).', Required: '.number_format($chunkTotalNetSalary, 2));

                            continue;
                        }

                        try {
                            \DB::table('payroll_jobs')->insert($chunk);
                        } catch (\Illuminate\Database\QueryException $e) {
                            // Handle unique constraint violations (MySQL and PostgreSQL) - insert individually for this chunk
                            if (app(\App\Services\ErrorClassificationService::class)->isUniqueConstraintViolation($e)) {
                                foreach ($chunk as $jobData) {
                                    try {
                                        // Re-check balance before individual insert in retry path
                                        $business->refresh();
                                        $individualBalance = $this->escrowService->getAvailableBalance($business, false, false);
                                        if ($individualBalance === 0 || $individualBalance < 0 || $individualBalance < $jobData['net_salary']) {
                                            Log::warning('Payroll job skipped in retry - insufficient balance', [
                                                'employee_id' => $jobData['employee_id'],
                                                'schedule_id' => $lockedSchedule->id,
                                                'balance' => $individualBalance,
                                                'required' => $jobData['net_salary'],
                                            ]);

                                            continue;
                                        }

                                        $lockedSchedule->payrollJobs()->create($jobData);
                                    } catch (\Exception $e2) {
                                        // Skip duplicates
                                        Log::debug('Payroll job already exists', [
                                            'employee_id' => $jobData['employee_id'],
                                            'schedule_id' => $lockedSchedule->id,
                                        ]);
                                    }
                                }
                            } else {
                                throw $e;
                            }
                        }
                    }

                    // Reload created jobs using unique combination and assign to settlement windows
                    // Optimize query to use composite indexes effectively (employee_id, pay_period_start, pay_period_end)
                    $employeeIds = array_unique(array_column($jobsToCreate, 'employee_id'));
                    $createdJobs = \App\Models\PayrollJob::where('payroll_schedule_id', $lockedSchedule->id)
                        ->whereIn('employee_id', $employeeIds)
                        ->where('pay_period_start', $payPeriodStart->format('Y-m-d'))
                        ->where('pay_period_end', $payPeriodEnd->format('Y-m-d'))
                        ->where('status', 'pending')
                        ->select(['id', 'payroll_schedule_id', 'employee_id', 'pay_period_start', 'pay_period_end', 'status'])
                        ->get();

                    // Batch assign jobs to settlement windows (optimized for performance)
                    if ($createdJobs->isNotEmpty()) {
                        try {
                            $jobIds = $createdJobs->pluck('id')->toArray();
                            $this->settlementService->assignPayrollJobsBulk($jobIds, 'hourly');
                        } catch (\Exception $e) {
                            Log::warning('Failed to batch assign payroll jobs to settlement window', [
                                'job_count' => $createdJobs->count(),
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }

                    // Collect batches to dispatch after the transaction commits so jobs are pushed to the queue
                    // only after PayrollJob rows exist. Use database connection explicitly so the worker picks them up.
                    $chunkSize = max(1, $batchSize);
                    $batchesToDispatch = $createdJobs->chunk($chunkSize)->map(fn ($jobBatch) => $jobBatch->pluck('id')->toArray())->values()->all();
                    $totalJobs = $createdJobs->count();

                    DB::afterCommit(function () use ($batchesToDispatch) {
                        try {
                            foreach ($batchesToDispatch as $jobIds) {
                                try {
                                    BatchProcessPayrollJob::dispatch($jobIds)->onConnection('database')->onQueue('high');
                                    Log::info('Batch payroll job dispatched to queue', [
                                        'job_count' => count($jobIds),
                                        'job_ids' => $jobIds,
                                    ]);
                                } catch (\Exception $e) {
                                    Log::error('Failed to dispatch batch payroll job to queue', [
                                        'job_count' => count($jobIds),
                                        'error' => $e->getMessage(),
                                        'trace' => $e->getTraceAsString(),
                                    ]);

                                    $payrollJobs = PayrollJob::whereIn('id', $jobIds)->get();
                                    foreach ($payrollJobs as $payrollJob) {
                                        try {
                                            ProcessPayrollJob::dispatch($payrollJob)->onConnection('database')->onQueue('high');
                                            Log::info('Payroll job dispatched to queue (fallback)', ['payroll_job_id' => $payrollJob->id]);
                                        } catch (\Exception $e2) {
                                            Log::error('Failed to dispatch payroll job to queue', [
                                                'payroll_job_id' => $payrollJob->id,
                                                'error' => $e2->getMessage(),
                                            ]);
                                        }
                                    }
                                }
                            }
                        } catch (\Throwable $e) {
                            // Do not rethrow: log and allow command to exit 0 so scheduler does not mark as failed.
                            Log::error('Payroll afterCommit dispatch failed', [
                                'error' => $e->getMessage(),
                                'trace' => $e->getTraceAsString(),
                            ]);
                        }
                    });
                } catch (\Exception $e) {
                    Log::error('Batch payroll job creation failed', [
                        'schedule_id' => $lockedSchedule->id,
                        'error' => $e->getMessage(),
                    ]);

                    throw $e;
                }
            }

            // Schedule is already updated atomically in the claim transaction above
            // Log completion for informational purposes only
            if ($totalJobs > 0 || count($employees) === 0) {
                $this->logScheduleCompletion($lockedSchedule, $totalJobs);
            }

            // Clear memory: unset large collections that are no longer needed
            unset($employees, $jobsToCreate);

            return $totalJobs;
        });
    }

    /**
     * Log schedule completion (informational only - schedule already updated atomically)
     *
     * The schedule's last_run_at and next_run_at are already set atomically in the claim transaction.
     * This method only provides logging and user feedback.
     */
    protected function logScheduleCompletion(PayrollSchedule $schedule, int $jobsCreated): void
    {
        // Reload to get the updated next_run_at
        $schedule->refresh();

        if ($schedule->isOneTime()) {
            Log::info('One-time payroll schedule processed and auto-cancelled', [
                'schedule_id' => $schedule->id,
                'jobs_created' => $jobsCreated,
            ]);

            $this->info("Schedule #{$schedule->id} (one-time) processed and auto-cancelled. Created {$jobsCreated} job(s).");
        } else {
            $nextRun = $schedule->next_run_at;

            Log::info('Recurring payroll schedule processed', [
                'schedule_id' => $schedule->id,
                'jobs_created' => $jobsCreated,
                'next_run_at' => $nextRun?->format('Y-m-d H:i:s'),
            ]);

            if ($nextRun) {
                $this->info("Schedule #{$schedule->id} processed. Created {$jobsCreated} job(s). Next run: {$nextRun->format('Y-m-d H:i:s')}");
            } else {
                $this->info("Schedule #{$schedule->id} processed. Created {$jobsCreated} job(s).");
            }
        }
    }

    /**
     * Start a lock heartbeat timer to extend lock expiration during long operations.
     *
     * @param  string  $lockKey  The lock key
     * @param  int  $lockTTL  Lock TTL in seconds
     * @param  int  $heartbeatInterval  Interval between heartbeats in seconds
     * @return \Illuminate\Support\Testing\Fakes\EventFake|null Timer instance or null if not needed
     */
    protected function startLockHeartbeat(string $lockKey, int $lockTTL, int $heartbeatInterval): ?object
    {
        // Only start heartbeat if operation might take longer than lock TTL
        if ($heartbeatInterval >= $lockTTL) {
            return null; // No heartbeat needed
        }

        // Use Laravel's scheduler or a simple timer
        // For now, we'll use a simple approach with pcntl_alarm if available
        // Otherwise, we'll rely on the lock TTL being sufficient

        // Register a shutdown function to send final heartbeat
        register_shutdown_function(function () use ($lockKey, $lockTTL) {
            try {
                $this->lockService->heartbeat($lockKey, $lockTTL);
            } catch (\Exception $e) {
                // Ignore errors during shutdown
            }
        });

        // Return a simple timer object for stopping
        return new class($this->lockService, $lockKey, $lockTTL, $heartbeatInterval)
        {
            protected $lockService;

            protected $lockKey;

            protected $lockTTL;

            protected $heartbeatInterval;

            protected $running = true;

            protected $timer = null;

            public function __construct($lockService, $lockKey, $lockTTL, $heartbeatInterval)
            {
                $this->lockService = $lockService;
                $this->lockKey = $lockKey;
                $this->lockTTL = $lockTTL;
                $this->heartbeatInterval = $heartbeatInterval;

                // Start periodic heartbeat in background
                $this->start();
            }

            protected function start(): void
            {
                if (function_exists('pcntl_alarm')) {
                    // Use pcntl_alarm for periodic heartbeats
                    pcntl_alarm($this->heartbeatInterval);
                    pcntl_signal(SIGALRM, function () {
                        if ($this->running) {
                            $this->lockService->heartbeat($this->lockKey, $this->lockTTL);
                            if ($this->running) {
                                pcntl_alarm($this->heartbeatInterval);
                            }
                        }
                    });
                }
            }

            public function stop(): void
            {
                $this->running = false;
                if (function_exists('pcntl_alarm')) {
                    pcntl_alarm(0); // Cancel alarm
                }
            }
        };
    }

    /**
     * Calculate adaptive batch size based on system load, memory, and queue depth
     *
     * Adapts batch size dynamically to optimize throughput while preventing resource exhaustion.
     * Considers: available memory, current memory usage, queue depth, and base configuration.
     *
     * @return int Optimal batch size
     */
    protected function calculateAdaptiveBatchSize(): int
    {
        $baseBatchSize = (int) config('payroll.batch_size', 100);
        $batchSize = $baseBatchSize;

        // Factor 1: Memory constraints
        $memoryLimit = ini_get('memory_limit');
        if ($memoryLimit && $memoryLimit !== '-1') {
            $memoryBytes = $this->parseMemoryLimit($memoryLimit);
            $currentMemoryUsage = memory_get_usage(true);
            $availableMemory = $memoryBytes - $currentMemoryUsage;
            $memoryUsagePercent = ($currentMemoryUsage / $memoryBytes) * 100;

            // Reduce batch size if memory is constrained
            if ($memoryBytes < 512 * 1024 * 1024) {
                $batchSize = max(50, (int) ($batchSize * 0.5));
            } elseif ($memoryUsagePercent > 80) {
                // High memory usage - reduce batch size
                $batchSize = max(50, (int) ($batchSize * 0.7));
            } elseif ($availableMemory < 100 * 1024 * 1024) {
                // Less than 100MB available - reduce batch size
                $batchSize = max(50, (int) ($batchSize * 0.6));
            }
        }

        // Factor 2: Queue depth (high queue depth = reduce batch size to process faster)
        try {
            $queueDepth = DB::table('jobs')
                ->whereIn('queue', ['high', 'normal', 'default'])
                ->count();

            // If queue depth is very high (>1000), reduce batch size to process jobs faster
            if ($queueDepth > 1000) {
                $batchSize = max(50, (int) ($batchSize * 0.6));
            } elseif ($queueDepth > 500) {
                $batchSize = max(75, (int) ($batchSize * 0.8));
            } elseif ($queueDepth < 100) {
                // Low queue depth - can use larger batches
                $batchSize = min(200, (int) ($batchSize * 1.2));
            }
        } catch (\Exception $e) {
            // If queue table doesn't exist or query fails, continue with current batch size
            Log::debug('Could not check queue depth for adaptive batching', [
                'error' => $e->getMessage(),
            ]);
        }

        // Factor 3: Peak memory usage (if approaching limit, reduce batch size)
        $peakMemory = memory_get_peak_usage(true);
        if ($memoryLimit && $memoryLimit !== '-1') {
            $peakMemoryPercent = ($peakMemory / $memoryBytes) * 100;
            if ($peakMemoryPercent > 90) {
                $batchSize = max(50, (int) ($batchSize * 0.5));
            }
        }

        // Factor 4: CPU load (if available via sys_getloadavg)
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            if ($load && isset($load[0])) {
                $cpuLoad = $load[0]; // 1-minute load average
                $cpuCores = (int) shell_exec('nproc') ?: 1; // Try to get CPU core count
                $loadPercent = ($cpuLoad / max($cpuCores, 1)) * 100;

                // Reduce batch size if CPU is heavily loaded
                if ($loadPercent > 90) {
                    $batchSize = max(25, (int) ($batchSize * 0.6));
                } elseif ($loadPercent > 70) {
                    $batchSize = max(25, (int) ($batchSize * 0.8));
                } elseif ($loadPercent < 30) {
                    // Low CPU load - can use larger batches
                    $batchSize = min(200, (int) ($batchSize * 1.1));
                }
            }
        }

        // Factor 5: Database connection pool awareness
        // Consider active database connections (if we can query it)
        try {
            $driver = config('database.default');
            $connectionName = config("database.connections.{$driver}.driver");

            // Database-specific connection check
            if ($connectionName === 'mysql' || $connectionName === 'mariadb') {
                $activeConnections = DB::select('SHOW STATUS WHERE Variable_name = ?', ['Threads_connected']);
                if (! empty($activeConnections) && isset($activeConnections[0]->Value)) {
                    $connections = (int) $activeConnections[0]->Value;
                    $maxConnections = (int) config("database.connections.{$driver}.max_connections", 151);
                    $connectionPercent = $maxConnections > 0 ? ($connections / $maxConnections) * 100 : 0;

                    // Reduce batch size if connection pool is getting full
                    if ($connectionPercent > 80) {
                        $batchSize = max(25, (int) ($batchSize * 0.7));
                    } elseif ($connectionPercent > 60) {
                        $batchSize = max(25, (int) ($batchSize * 0.9));
                    }
                }
            } elseif ($connectionName === 'pgsql') {
                $result = DB::selectOne('
                    SELECT 
                        count(*) as active_connections,
                        (SELECT setting::int FROM pg_settings WHERE name = \'max_connections\') as max_connections
                    FROM pg_stat_activity 
                    WHERE datname = current_database()
                ');
                if ($result && isset($result->active_connections)) {
                    $connections = (int) $result->active_connections;
                    $maxConnections = (int) ($result->max_connections ?? 100);
                    $connectionPercent = $maxConnections > 0 ? ($connections / $maxConnections) * 100 : 0;

                    // Reduce batch size if connection pool is getting full
                    if ($connectionPercent > 80) {
                        $batchSize = max(25, (int) ($batchSize * 0.7));
                    } elseif ($connectionPercent > 60) {
                        $batchSize = max(25, (int) ($batchSize * 0.9));
                    }
                }
            }
        } catch (\Exception $e) {
            // If query fails (e.g., not MySQL), continue with current batch size
            Log::debug('Could not check database connection pool for adaptive batching', [
                'error' => $e->getMessage(),
            ]);
        }

        // Ensure batch size is within reasonable bounds (minimum 25 for viability)
        $batchSize = max(25, min(200, $batchSize));

        Log::debug('Adaptive batch size calculated', [
            'base_batch_size' => $baseBatchSize,
            'calculated_batch_size' => $batchSize,
            'memory_usage_percent' => $memoryUsagePercent ?? null,
            'queue_depth' => $queueDepth ?? null,
            'cpu_load' => $loadPercent ?? null,
            'db_connections_percent' => $connectionPercent ?? null,
        ]);

        return (int) $batchSize;
    }

    /**
     * Parse memory limit string to bytes
     */
    protected function parseMemoryLimit(string $limit): int
    {
        $limit = trim($limit);
        $last = strtolower($limit[strlen($limit) - 1]);
        $value = (int) $limit;

        switch ($last) {
            case 'g':
                $value *= 1024;
                // no break
            case 'm':
                $value *= 1024;
                // no break
            case 'k':
                $value *= 1024;
        }

        return $value;
    }
}
