<?php

namespace App\Console\Commands;

use App\Jobs\BatchProcessPaymentJob;
use App\Models\Business;
use App\Models\PaymentJob;
use App\Models\PaymentSchedule;
use App\Services\BatchProcessingService;
use App\Services\ErrorClassificationService;
use App\Services\EscrowService;
use App\Services\LockService;
use App\Services\SettlementService;
use App\Services\SouthAfricaHolidayService;
use App\Traits\RetriesTransactions;
use Cron\CronExpression;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessScheduledPayments extends Command
{
    use RetriesTransactions;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payments:process-scheduled';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process all due payment schedules';

    public function __construct(
        protected SouthAfricaHolidayService $holidayService,
        protected BatchProcessingService $batchProcessingService,
        protected SettlementService $settlementService,
        protected LockService $lockService,
        protected EscrowService $escrowService
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // Only process generic payment schedules (not payroll)
        // Optimized query - only select needed columns
        $dueSchedules = PaymentSchedule::due()
            ->ofType('generic')
            ->select(['id', 'business_id', 'amount', 'currency', 'frequency', 'next_run_at', 'last_run_at', 'status', 'schedule_type'])
            ->with([
                'recipients:id,payment_schedule_id,name,email',
                'business:id,status,escrow_balance',
            ])
            ->get();

        if ($dueSchedules->isEmpty()) {
            $this->info('No due payment schedules found.');

            return Command::SUCCESS;
        }

        $this->info("Found {$dueSchedules->count()} due payment schedule(s).");

        $totalJobs = 0;
        $processedSchedules = 0;
        $batchSize = $this->calculateAdaptiveBatchSize();

        foreach ($dueSchedules as $schedule) {
            $business = $schedule->business;
            if ($business && $business->status !== 'active') {
                $this->warn("Schedule #{$schedule->id} belongs to a {$business->status} business. Skipping.");

                continue;
            }
            if ($schedule->recipients->isEmpty()) {
                $this->warn("Schedule #{$schedule->id} has no recipients assigned. Skipping.");

                continue;
            }

            $lockKey = "payment_schedule_{$schedule->id}_execution";
            $lockTTL = (int) config('payroll.schedule_lock_ttl', 600);
            $heartbeatInterval = (int) config('payroll.schedule_lock_heartbeat_interval', 300);
            $lockAcquired = $this->lockService->acquire($lockKey, 10, $lockTTL);

            if (! $lockAcquired) {
                $this->warn("Schedule #{$schedule->id} is already being processed by another instance. Skipping.");
                Log::info('Payment schedule execution skipped - lock already held', ['schedule_id' => $schedule->id]);

                continue;
            }

            try {
                $heartbeatTimer = $this->startLockHeartbeat($lockKey, $lockTTL, $heartbeatInterval);
                try {
                    $scheduleJobs = $this->processSchedule($schedule, $batchSize);
                    $totalJobs += $scheduleJobs;
                    $processedSchedules++;
                } finally {
                    if ($heartbeatTimer) {
                        $heartbeatTimer->stop();
                    }
                }
            } finally {
                $this->lockService->release($lockKey);
            }
        }

        $this->info("Dispatched {$totalJobs} payment job(s) from {$processedSchedules} schedule(s).");

        return Command::SUCCESS;
    }

    /**
     * Process a single payment schedule with atomic claim (lock row, claim run, then create jobs).
     *
     * @return int Number of jobs created and dispatched
     */
    protected function processSchedule(PaymentSchedule $schedule, int $batchSize): int
    {
        $maxRetries = (int) config('payroll.transaction_max_retries', 3);

        return $this->retryTransaction(function () use ($schedule, $batchSize) {
            $lockedSchedule = PaymentSchedule::where('id', $schedule->id)
                ->with('recipients:id,payment_schedule_id,name,email')
                ->lockForUpdate()
                ->first();

            if (! $lockedSchedule) {
                Log::warning('Payment schedule not found', ['schedule_id' => $schedule->id]);

                return 0;
            }

            $updated = 0;
            if ($lockedSchedule->isOneTime()) {
                $updated = PaymentSchedule::where('id', $schedule->id)
                    ->whereNull('last_run_at')
                    ->update([
                        'last_run_at' => now(),
                        'next_run_at' => null,
                        'status' => 'cancelled',
                        'updated_at' => now(),
                    ]);
            } else {
                $nextRun = $this->computeNextRunAt($lockedSchedule);
                $updated = PaymentSchedule::where('id', $schedule->id)
                    ->where(function ($query) {
                        $query->whereNull('last_run_at')
                            ->orWhereColumn('last_run_at', '<', 'next_run_at');
                    })
                    ->update([
                        'last_run_at' => now(),
                        'next_run_at' => $nextRun,
                        'updated_at' => now(),
                    ]);
            }

            if ($updated === 0) {
                Log::info('Payment schedule already processed by another process', ['schedule_id' => $schedule->id]);

                return 0;
            }

            // Check escrow balance before creating jobs
            // Lock business row to prevent concurrent balance checks
            $business = Business::where('id', $lockedSchedule->business_id)
                ->lockForUpdate()
                ->first();

            if (! $business) {
                Log::warning('Business not found for payment schedule', [
                    'schedule_id' => $lockedSchedule->id,
                    'business_id' => $lockedSchedule->business_id,
                ]);

                return 0;
            }

            // CRITICAL CHECK #1: escrow_balance must be NOT NULL
            // This prevents creating jobs when balance is NULL (should be prevented by database constraint, but check here too)
            if ($business->escrow_balance === null) {
                Log::warning('Payment schedule skipped - escrow balance is NULL', [
                    'schedule_id' => $lockedSchedule->id,
                    'business_id' => $business->id,
                    'escrow_balance' => null,
                ]);

                $this->warn("Skipping schedule #{$lockedSchedule->id} - escrow balance is NULL. This is a critical error - active businesses must have a non-NULL escrow balance.");

                return 0;
            }

            // Get available balance with locked row (no refresh needed since we just locked it)
            $escrowBalance = $this->escrowService->getAvailableBalance($business, false, false);
            $recipientCount = $lockedSchedule->recipients->count();
            $totalAmountRequired = $lockedSchedule->amount * $recipientCount;

            // CRITICAL CHECK #2: escrow balance must be greater than zero (explicit zero check)
            // This prevents creating jobs when balance is exactly zero
            if ($escrowBalance === 0) {
                Log::warning('Payment schedule skipped - escrow balance is exactly zero', [
                    'schedule_id' => $lockedSchedule->id,
                    'business_id' => $business->id,
                    'escrow_balance' => $escrowBalance,
                    'total_amount_required' => $totalAmountRequired,
                ]);

                $this->warn("Skipping schedule #{$lockedSchedule->id} - escrow balance is zero");

                return 0;
            }

            // CRITICAL CHECK #3: escrow balance must not be negative
            if ($escrowBalance < 0) {
                Log::warning('Payment schedule skipped - escrow balance is negative', [
                    'schedule_id' => $lockedSchedule->id,
                    'business_id' => $business->id,
                    'escrow_balance' => $escrowBalance,
                    'total_amount_required' => $totalAmountRequired,
                ]);

                $this->warn("Skipping schedule #{$lockedSchedule->id} - escrow balance is negative (balance: {$escrowBalance})");

                return 0;
            }

            // CRITICAL CHECK #4: available balance must be sufficient for total amount required
            if ($escrowBalance < $totalAmountRequired) {
                Log::warning('Payment schedule skipped - insufficient escrow balance', [
                    'schedule_id' => $lockedSchedule->id,
                    'business_id' => $business->id,
                    'escrow_balance' => $escrowBalance,
                    'required_amount' => $totalAmountRequired,
                    'shortfall' => $totalAmountRequired - $escrowBalance,
                ]);

                $this->warn("Skipping schedule #{$lockedSchedule->id} - insufficient escrow balance. Available: ".number_format($escrowBalance, 2).', Required: '.number_format($totalAmountRequired, 2));

                return 0;
            }

            // Generate schedule run ID for audit trail (all jobs from this run share the same ID)
            $scheduleRunId = \Illuminate\Support\Str::uuid()->toString();

            $jobsToCreate = [];
            foreach ($lockedSchedule->recipients as $recipient) {
                $jobsToCreate[] = [
                    'payment_schedule_id' => $lockedSchedule->id,
                    'schedule_run_id' => $scheduleRunId,
                    'recipient_id' => $recipient->id,
                    'amount' => $lockedSchedule->amount,
                    'currency' => $lockedSchedule->currency,
                    'status' => 'pending',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            // CRITICAL: Calculate total amount for entire batch before any inserts
            // This prevents creating jobs when total batch exceeds balance
            $totalBatchAmount = array_sum(array_column($jobsToCreate, 'amount'));

            // Explicit check: total batch amount must not exceed available balance
            if ($escrowBalance < $totalBatchAmount) {
                Log::warning('Payment schedule skipped - total batch amount exceeds available balance', [
                    'schedule_id' => $lockedSchedule->id,
                    'business_id' => $business->id,
                    'escrow_balance' => $escrowBalance,
                    'total_batch_amount' => $totalBatchAmount,
                    'shortfall' => $totalBatchAmount - $escrowBalance,
                    'job_count' => count($jobsToCreate),
                ]);

                $this->warn("Skipping schedule #{$lockedSchedule->id} - total batch amount exceeds available balance. Available: ".number_format($escrowBalance, 2).', Total batch: '.number_format($totalBatchAmount, 2));

                return 0;
            }

            $chunkSize = max($batchSize, 200);
            $chunks = array_chunk($jobsToCreate, $chunkSize);
            $createdCount = 0;

            foreach ($chunks as $chunk) {
                // CRITICAL: Re-check balance before each chunk insert with locked business row
                // This prevents race conditions where balance might have changed between initial check and insert
                $business->refresh();
                $chunkTotalAmount = array_sum(array_column($chunk, 'amount'));

                // CRITICAL CHECK: escrow_balance must be NOT NULL (re-check after refresh)
                if ($business->escrow_balance === null) {
                    Log::warning('Payment job chunk skipped - escrow balance is NULL after refresh', [
                        'schedule_id' => $lockedSchedule->id,
                        'business_id' => $business->id,
                        'escrow_balance' => null,
                        'chunk_total_amount' => $chunkTotalAmount,
                        'chunk_size' => count($chunk),
                    ]);

                    $this->warn('Skipping chunk of '.count($chunk).' payment jobs - escrow balance is NULL');

                    continue;
                }

                $currentBalance = $this->escrowService->getAvailableBalance($business, false, false);

                // CRITICAL CHECK: escrow balance must be greater than zero (explicit zero check)
                if ($currentBalance === 0) {
                    Log::warning('Payment job chunk skipped - escrow balance is exactly zero', [
                        'schedule_id' => $lockedSchedule->id,
                        'business_id' => $business->id,
                        'escrow_balance' => $currentBalance,
                        'chunk_total_amount' => $chunkTotalAmount,
                        'chunk_size' => count($chunk),
                    ]);

                    $this->warn('Skipping chunk of '.count($chunk).' payment jobs - escrow balance is zero');

                    continue;
                }

                if ($currentBalance < 0) {
                    Log::warning('Payment job chunk skipped - escrow balance is negative', [
                        'schedule_id' => $lockedSchedule->id,
                        'business_id' => $business->id,
                        'escrow_balance' => $currentBalance,
                        'chunk_total_amount' => $chunkTotalAmount,
                        'chunk_size' => count($chunk),
                    ]);

                    $this->warn('Skipping chunk of '.count($chunk)." payment jobs - escrow balance is negative (balance: {$currentBalance})");

                    continue;
                }

                // Explicit check: available balance must be sufficient for chunk total
                if ($currentBalance < $chunkTotalAmount) {
                    Log::warning('Payment job chunk skipped - insufficient escrow balance', [
                        'schedule_id' => $lockedSchedule->id,
                        'business_id' => $business->id,
                        'escrow_balance' => $currentBalance,
                        'chunk_total_amount' => $chunkTotalAmount,
                        'shortfall' => $chunkTotalAmount - $currentBalance,
                        'chunk_size' => count($chunk),
                    ]);

                    $this->warn('Skipping chunk of '.count($chunk).' payment jobs - insufficient escrow balance. Available: '.number_format($currentBalance, 2).', Required: '.number_format($chunkTotalAmount, 2));

                    continue;
                }

                try {
                    DB::table('payment_jobs')->insert($chunk);
                    $createdCount += count($chunk);
                } catch (\Illuminate\Database\QueryException $e) {
                    if (app(ErrorClassificationService::class)->isUniqueConstraintViolation($e)) {
                        foreach ($chunk as $jobData) {
                            try {
                                // Re-check balance before individual insert in retry path
                                $business->refresh();
                                $individualBalance = $this->escrowService->getAvailableBalance($business, false, false);
                                if ($individualBalance === 0 || $individualBalance < 0 || $individualBalance < $jobData['amount']) {
                                    Log::warning('Payment job skipped in retry - insufficient balance', [
                                        'recipient_id' => $jobData['recipient_id'],
                                        'schedule_id' => $jobData['payment_schedule_id'],
                                        'balance' => $individualBalance,
                                        'required' => $jobData['amount'],
                                    ]);

                                    continue;
                                }

                                PaymentSchedule::find($jobData['payment_schedule_id'])
                                    ?->paymentJobs()->create($jobData);
                                $createdCount++;
                            } catch (\Exception $e2) {
                                Log::debug('Payment job already exists', [
                                    'recipient_id' => $jobData['recipient_id'],
                                    'schedule_id' => $jobData['payment_schedule_id'],
                                ]);
                            }
                        }
                    } else {
                        throw $e;
                    }
                }
            }

            if ($createdCount === 0) {
                return 0;
            }

            $recipientIds = array_column($jobsToCreate, 'recipient_id');
            $createdJobs = PaymentJob::where('payment_schedule_id', $lockedSchedule->id)
                ->where('status', 'pending')
                ->whereIn('recipient_id', $recipientIds)
                ->whereDate('created_at', today())
                ->select(['id', 'payment_schedule_id', 'recipient_id', 'status', 'created_at'])
                ->get();

            if ($createdJobs->isNotEmpty()) {
                try {
                    $this->settlementService->assignPaymentJobsBulk($createdJobs->pluck('id')->toArray(), 'hourly');
                } catch (\Exception $e) {
                    Log::warning('Failed to batch assign payment jobs to settlement window', [
                        'schedule_id' => $lockedSchedule->id,
                        'error' => $e->getMessage(),
                    ]);
                }
                $createdJobs->chunk($batchSize)->each(function ($jobBatch) {
                    $jobIds = $jobBatch->pluck('id')->toArray();
                    try {
                        BatchProcessPaymentJob::dispatch($jobIds)->onQueue('normal');
                        Log::info('Batch payment job dispatched to queue', [
                            'job_count' => count($jobIds),
                            'job_ids' => $jobIds,
                        ]);
                    } catch (\Exception $e) {
                        Log::error('Failed to dispatch batch payment job to queue', [
                            'job_count' => count($jobIds),
                            'error' => $e->getMessage(),
                        ]);
                    }
                });
            }

            return $createdCount;
        }, $maxRetries);
    }

    protected function computeNextRunAt(PaymentSchedule $schedule): \Carbon\Carbon
    {
        $cron = CronExpression::factory($schedule->frequency);
        $nextRun = \Carbon\Carbon::instance($cron->getNextRunDate(now(config('app.timezone'))));
        if (! $this->holidayService->isBusinessDay($nextRun)) {
            $originalTime = $nextRun->format('H:i');
            $nextRun = $this->holidayService->getNextBusinessDay($nextRun);
            $nextRun->setTime((int) explode(':', $originalTime)[0], (int) explode(':', $originalTime)[1]);
        }

        return $nextRun;
    }

    protected function startLockHeartbeat(string $lockKey, int $lockTTL, int $heartbeatInterval): ?object
    {
        if ($heartbeatInterval >= $lockTTL) {
            return null;
        }
        register_shutdown_function(function () use ($lockKey, $lockTTL) {
            try {
                $this->lockService->heartbeat($lockKey, $lockTTL);
            } catch (\Exception $e) {
            }
        });

        return new class($this->lockService, $lockKey, $lockTTL, $heartbeatInterval)
        {
            protected $lockService;

            protected $lockKey;

            protected $lockTTL;

            protected $heartbeatInterval;

            protected $running = true;

            public function __construct($lockService, $lockKey, $lockTTL, $heartbeatInterval)
            {
                $this->lockService = $lockService;
                $this->lockKey = $lockKey;
                $this->lockTTL = $lockTTL;
                $this->heartbeatInterval = $heartbeatInterval;
                $this->start();
            }

            protected function start(): void
            {
                if (function_exists('pcntl_alarm')) {
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
                    pcntl_alarm(0);
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

    /**
     * Update schedule after processing
     */
    protected function updateScheduleAfterProcessing(PaymentSchedule $schedule): void
    {
        // Handle one-time vs recurring schedules
        if ($schedule->isOneTime()) {
            // Auto-cancel one-time schedules after execution
            $schedule->update([
                'status' => 'cancelled',
                'next_run_at' => null,
                'last_run_at' => now(),
            ]);

            Log::info('One-time payment schedule auto-cancelled after execution', [
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

                    Log::info('Payment schedule next run adjusted to skip weekend/holiday', [
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
                Log::error('Failed to calculate next run time for schedule', [
                    'schedule_id' => $schedule->id,
                    'frequency' => $schedule->frequency,
                    'error' => $e->getMessage(),
                ]);

                $this->error("Failed to calculate next run time for schedule #{$schedule->id}: {$e->getMessage()}");
            }
        }
    }
}
