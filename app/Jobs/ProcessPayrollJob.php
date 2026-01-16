<?php

namespace App\Jobs;

use App\Mail\PaymentFailedEmail;
use App\Mail\PaymentSuccessEmail;
use App\Models\PayrollJob;
use App\Services\EmailService;
use App\Services\PaymentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessPayrollJob implements ShouldQueue
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
        public PayrollJob $payrollJob
    ) {
    }

    /**
     * Execute the job.
     */
    public function handle(PaymentService $paymentService): void
    {
        try {
            $success = $paymentService->processPayrollJob($this->payrollJob);

            if ($success) {
                // Refresh to get updated status
                $this->payrollJob->refresh();
                
                // Send success email
                $user = $this->payrollJob->payrollSchedule->business->owner;
                $emailService = app(EmailService::class);
                $emailService->send($user, new PaymentSuccessEmail($user, $this->payrollJob), 'payroll_success');
            } else {
                Log::warning('Payroll job failed', [
                    'payroll_job_id' => $this->payrollJob->id,
                    'attempt' => $this->attempts(),
                ]);

                // Throw exception to trigger retry
                if ($this->attempts() < $this->tries) {
                    throw new \Exception('Payroll processing failed. Retrying...');
                }
            }
        } catch (\Exception $e) {
            Log::error('Payroll job exception', [
                'payroll_job_id' => $this->payrollJob->id,
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
        $this->payrollJob->update([
            'status' => 'failed',
            'error_message' => $exception->getMessage(),
        ]);

        Log::error('Payroll job permanently failed', [
            'payroll_job_id' => $this->payrollJob->id,
            'exception' => $exception->getMessage(),
        ]);

        // Send failure email
        $user = $this->payrollJob->payrollSchedule->business->owner;
        $emailService = app(EmailService::class);
        $emailService->send($user, new PaymentFailedEmail($user, $this->payrollJob), 'payroll_failed');
    }
}
