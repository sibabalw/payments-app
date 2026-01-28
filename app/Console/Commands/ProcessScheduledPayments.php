<?php

namespace App\Console\Commands;

use App\Jobs\BatchProcessPaymentJob;
use App\Jobs\ProcessPaymentJob;
use App\Models\PaymentSchedule;
use App\Services\BatchProcessingService;
use App\Services\SettlementService;
use App\Services\SouthAfricaHolidayService;
use Cron\CronExpression;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessScheduledPayments extends Command
{
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
        protected SettlementService $settlementService
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
            ->select(['id', 'business_id', 'amount', 'currency', 'frequency', 'next_run_at', 'last_run_at', 'status'])
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
        // Adaptive batch sizing based on system load, memory, and queue depth
        $batchSize = $this->calculateAdaptiveBatchSize();

        // Process schedules in batches
        $dueSchedules->chunk(50)->each(function ($scheduleBatch) use (&$totalJobs, $batchSize) {
            $jobsToCreate = [];

            foreach ($scheduleBatch as $schedule) {
                // Skip if business is banned or suspended - check status directly for efficiency
                // Business is already eager loaded, so direct status check is faster than method call
                $business = $schedule->business;
                if ($business && $business->status !== 'active') {
                    $this->warn("Schedule #{$schedule->id} belongs to a {$business->status} business. Skipping.");

                    continue;
                }

                $recipients = $schedule->recipients;

                if ($recipients->isEmpty()) {
                    $this->warn("Schedule #{$schedule->id} has no recipients assigned. Skipping.");

                    continue;
                }

                // Prepare payment jobs for batch insert
                foreach ($recipients as $recipient) {
                    $jobsToCreate[] = [
                        'payment_schedule_id' => $schedule->id,
                        'recipient_id' => $recipient->id,
                        'amount' => $schedule->amount,
                        'currency' => $schedule->currency,
                        'status' => 'pending',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }

            // Bulk insert payment jobs
            if (! empty($jobsToCreate)) {
                try {
                    // Larger chunks (200) for better performance while staying within query limits
                    $chunks = array_chunk($jobsToCreate, max($batchSize, 200));

                    foreach ($chunks as $chunk) {
                        try {
                            \DB::table('payment_jobs')->insert($chunk);
                        } catch (\Illuminate\Database\QueryException $e) {
                            // Handle unique constraint violations - insert individually for this chunk
                            if ($e->getCode() === '23000' || str_contains($e->getMessage(), 'Duplicate entry')) {
                                foreach ($chunk as $jobData) {
                                    try {
                                        $schedule = PaymentSchedule::find($jobData['payment_schedule_id']);
                                        if ($schedule) {
                                            $schedule->paymentJobs()->create($jobData);
                                        }
                                    } catch (\Exception $e2) {
                                        // Skip duplicates
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

                    // Reload created jobs and assign to settlement windows
                    // Optimize query to use composite indexes effectively
                    $scheduleIds = array_unique(array_column($jobsToCreate, 'payment_schedule_id'));
                    $recipientIds = array_unique(array_column($jobsToCreate, 'recipient_id'));

                    $createdJobs = \App\Models\PaymentJob::whereIn('payment_schedule_id', $scheduleIds)
                        ->where('status', 'pending')
                        ->whereIn('recipient_id', $recipientIds)
                        ->whereDate('created_at', today())
                        ->select(['id', 'payment_schedule_id', 'recipient_id', 'status', 'created_at'])
                        ->get();

                    // Batch assign jobs to settlement windows (optimized for performance)
                    if ($createdJobs->isNotEmpty()) {
                        try {
                            $jobIds = $createdJobs->pluck('id')->toArray();
                            $this->settlementService->assignPaymentJobsBulk($jobIds, 'hourly');
                        } catch (\Exception $e) {
                            Log::warning('Failed to batch assign payment jobs to settlement window', [
                                'job_count' => $createdJobs->count(),
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }

                    // Dispatch batch jobs instead of individual jobs (bank-grade performance)
                    $createdJobs->chunk($batchSize)->each(function ($jobBatch) use (&$totalJobs) {
                        $jobIds = $jobBatch->pluck('id')->toArray();

                        try {
                            // Dispatch batch job on normal priority queue
                            BatchProcessPaymentJob::dispatch($jobIds)->onQueue('normal');
                            $totalJobs += count($jobIds);

                            Log::info('Batch payment job dispatched to queue', [
                                'job_count' => count($jobIds),
                                'job_ids' => $jobIds,
                            ]);
                        } catch (\Exception $e) {
                            Log::error('Failed to dispatch batch payment job to queue', [
                                'job_count' => count($jobIds),
                                'error' => $e->getMessage(),
                            ]);

                            // Fallback: dispatch individual jobs
                            // foreach ($jobBatch as $paymentJob) {
                            //     try {
                            //         ProcessPaymentJob::dispatch($paymentJob)->onQueue('normal');
                            //         $totalJobs++;
                            //     } catch (\Exception $e2) {
                            //         Log::error('Failed to dispatch payment job to queue', [
                            //             'payment_job_id' => $paymentJob->id,
                            //             'error' => $e2->getMessage(),
                            //         ]);
                            //     }
                            // }
                        }
                    });
                } catch (\Exception $e) {
                    Log::error('Batch payment job creation failed', [
                        'error' => $e->getMessage(),
                    ]);

                    // Fallback to individual processing
                    foreach ($jobsToCreate as $jobData) {
                        try {
                            $schedule = PaymentSchedule::find($jobData['payment_schedule_id']);
                            if ($schedule) {
                                $paymentJob = $schedule->paymentJobs()->create($jobData);
                                ProcessPaymentJob::dispatch($paymentJob);
                                $totalJobs++;
                            }
                        } catch (\Exception $e2) {
                            Log::warning('Failed to create/dispatch payment job', [
                                'recipient_id' => $jobData['recipient_id'],
                                'error' => $e2->getMessage(),
                            ]);
                        }
                    }
                }
            }

            // Update schedules after processing
            foreach ($scheduleBatch as $schedule) {
                $this->updateScheduleAfterProcessing($schedule);
            }
        });

        $this->info("Dispatched {$totalJobs} payment job(s) to the queue.");

        return Command::SUCCESS;
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
            $activeConnections = DB::select('SHOW STATUS WHERE Variable_name = ?', ['Threads_connected']);
            if (! empty($activeConnections) && isset($activeConnections[0]->Value)) {
                $connections = (int) $activeConnections[0]->Value;
                $maxConnections = (int) config('database.connections.mysql.max_connections', 151);
                $connectionPercent = $maxConnections > 0 ? ($connections / $maxConnections) * 100 : 0;

                // Reduce batch size if connection pool is getting full
                if ($connectionPercent > 80) {
                    $batchSize = max(25, (int) ($batchSize * 0.7));
                } elseif ($connectionPercent > 60) {
                    $batchSize = max(25, (int) ($batchSize * 0.9));
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
