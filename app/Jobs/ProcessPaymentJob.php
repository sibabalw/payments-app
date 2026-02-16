<?php

namespace App\Jobs;

use App\Mail\PaymentFailedEmail;
use App\Mail\PaymentSuccessEmail;
use App\Models\PaymentJob;
use App\Services\EmailService;
use App\Services\ErrorClassificationService;
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
    ) {}

    /**
     * Execute the job.
     */
    public function handle(PaymentService $paymentService): void
    {
        // Cache error classification service instance and cache (shared across method)
        static $errorClassification = null;
        static $classificationCache = [];
        static $genericErrorType = null;

        if ($errorClassification === null) {
            $errorClassification = app(ErrorClassificationService::class);
        }

        try {
            $success = $paymentService->processPaymentJob($this->paymentJob);

            if ($success) {
                // Reload PaymentJob completely fresh from database to avoid any stale eager load metadata
                // This ensures no 'receiver' relationship metadata is carried over
                $freshPaymentJob = PaymentJob::query()
                    ->where('id', $this->paymentJob->id)
                    ->firstOrFail();

                // Load only what we need for the email
                $freshPaymentJob->load('paymentSchedule.business.owner');
                $user = $freshPaymentJob->paymentSchedule?->business?->owner;

                if ($user) {
                    $emailService = app(EmailService::class);
                    // Pass the fresh instance to avoid any serialization issues
                    $emailService->send($user, new PaymentSuccessEmail($user, $freshPaymentJob), 'payment_success');
                } else {
                    Log::warning('Cannot send payment success email: user not found', [
                        'payment_job_id' => $this->paymentJob->id,
                    ]);
                }
            } else {
                // Cache classification result for generic payment failure
                if ($genericErrorType === null) {
                    $genericErrorType = $errorClassification->classify(new \Exception('Payment processing failed'));
                }
                $errorType = $genericErrorType;

                Log::warning('Payment job failed', [
                    'payment_job_id' => $this->paymentJob->id,
                    'attempt' => $this->attempts(),
                    'error_type' => $errorType,
                ]);

                // Only retry if transient failure
                if ($errorType === 'transient' && $this->attempts() < $this->tries) {
                    throw new \Exception('Payment processing failed. Retrying...');
                }
            }
        } catch (\Exception $e) {
            // Cache classification results by exception message hash to avoid repeated classification
            $errorHash = md5(get_class($e).':'.$e->getMessage());

            if (! isset($classificationCache[$errorHash])) {
                $classificationCache[$errorHash] = [
                    'type' => $errorClassification->classify($e),
                    'category' => $errorClassification->getCategory($e),
                ];
            }

            $errorType = $classificationCache[$errorHash]['type'];
            $errorCategory = $classificationCache[$errorHash]['category'];

            Log::error('Payment job exception', [
                'payment_job_id' => $this->paymentJob->id,
                'exception' => $e->getMessage(),
                'attempt' => $this->attempts(),
                'error_type' => $errorType,
                'error_category' => $errorCategory,
            ]);

            // Only retry if transient failure
            if ($errorType === 'transient' && $this->attempts() < $this->tries) {
                throw $e;
            }

            // Permanent failure - mark as failed
            $this->paymentJob->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
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

        // Send failure email
        $user = $this->paymentJob->paymentSchedule->business->owner;
        $emailService = app(EmailService::class);
        $emailService->send($user, new PaymentFailedEmail($user, $this->paymentJob), 'payment_failed');
    }
}
