<?php

namespace App\Jobs;

use App\Models\PaymentJob;
use App\Services\PaymentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessPaymentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public PaymentJob $paymentJob
    ) {
    }

    /**
     * Execute the job.
     */
    public function handle(PaymentService $paymentService): void
    {
        try {
            $success = $paymentService->processPaymentJob($this->paymentJob);

            if (! $success) {
                Log::warning('Payment job failed', [
                    'payment_job_id' => $this->paymentJob->id,
                    'attempt' => $this->attempts(),
                ]);

                // Throw exception to trigger retry
                if ($this->attempts() < $this->tries) {
                    throw new \Exception('Payment processing failed. Retrying...');
                }
            }
        } catch (\Exception $e) {
            Log::error('Payment job exception', [
                'payment_job_id' => $this->paymentJob->id,
                'exception' => $e->getMessage(),
                'attempt' => $this->attempts(),
            ]);

            // Re-throw to trigger retry mechanism
            throw $e;
        }
    }

    /**
     * Calculate the number of seconds to wait before retrying the job.
     */
    public function backoff(): array
    {
        return [
            60,   // 1 minute
            300,  // 5 minutes
            900,  // 15 minutes
        ];
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        $this->paymentJob->update([
            'status' => 'failed',
            'error_message' => $exception->getMessage(),
        ]);

        Log::error('Payment job permanently failed', [
            'payment_job_id' => $this->paymentJob->id,
            'exception' => $exception->getMessage(),
        ]);
    }
}
