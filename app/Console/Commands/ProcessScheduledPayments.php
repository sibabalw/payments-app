<?php

namespace App\Console\Commands;

use App\Jobs\ProcessPaymentJob;
use App\Models\PaymentSchedule;
use Cron\CronExpression;
use Illuminate\Console\Command;
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

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // Only process generic payment schedules (not payroll)
        $dueSchedules = PaymentSchedule::due()
            ->ofType('generic')
            ->with(['recipients', 'business'])
            ->get();

        if ($dueSchedules->isEmpty()) {
            $this->info('No due payment schedules found.');
            return Command::SUCCESS;
        }

        $this->info("Found {$dueSchedules->count()} due payment schedule(s).");

        $totalJobs = 0;

        foreach ($dueSchedules as $schedule) {
            // Skip if business is banned or suspended
            $business = $schedule->business;
            if ($business && !$business->canPerformActions()) {
                $this->warn("Schedule #{$schedule->id} belongs to a {$business->status} business. Skipping.");
                continue;
            }

            $recipients = $schedule->recipients;

            if ($recipients->isEmpty()) {
                $this->warn("Schedule #{$schedule->id} has no recipients assigned. Skipping.");
                continue;
            }

            // Create payment jobs for each recipient (only when schedule executes, not during creation)
            foreach ($recipients as $recipient) {
                $paymentJob = $schedule->paymentJobs()->create([
                    'recipient_id' => $recipient->id,
                    'amount' => $schedule->amount,
                    'currency' => $schedule->currency,
                    'status' => 'pending',
                ]);

                // Dispatch job to queue (will be stored in jobs table)
                try {
                    ProcessPaymentJob::dispatch($paymentJob);
                    $totalJobs++;
                    
                    Log::info('Payment job dispatched to queue', [
                        'payment_job_id' => $paymentJob->id,
                        'recipient_id' => $recipient->id,
                        'schedule_id' => $schedule->id,
                    ]);
                } catch (\Exception $e) {
                    Log::error('Failed to dispatch payment job to queue', [
                        'payment_job_id' => $paymentJob->id,
                        'error' => $e->getMessage(),
                    ]);
                    
                    $this->error("Failed to dispatch payment job #{$paymentJob->id}: {$e->getMessage()}");
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

                Log::info('One-time payment schedule auto-cancelled after execution', [
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
                    Log::error('Failed to calculate next run time for schedule', [
                        'schedule_id' => $schedule->id,
                        'frequency' => $schedule->frequency,
                        'error' => $e->getMessage(),
                    ]);

                    $this->error("Failed to calculate next run time for schedule #{$schedule->id}: {$e->getMessage()}");
                }
            }
        }

        $this->info("Dispatched {$totalJobs} payment job(s) to the queue.");

        return Command::SUCCESS;
    }
}
