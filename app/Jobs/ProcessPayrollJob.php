<?php

namespace App\Jobs;

use App\Mail\PayrollFailedEmail;
use App\Mail\PayrollSuccessEmail;
use App\Mail\PayslipEmail;
use App\Models\PayrollJob;
use App\Services\EmailService;
use App\Services\PaymentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ProcessPayrollJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The maximum number of seconds a job can run before timing out.
     */
    public int $timeout = 300; // 5 minutes

    /**
     * Determine if the job should be retried based on the exception.
     */
    public function shouldRetry(\Throwable $exception): bool
    {
        $errorClassification = app(\App\Services\ErrorClassificationService::class);
        $errorType = $errorClassification->classify($exception);

        return $errorType === 'transient';
    }

    /**
     * Create a new job instance.
     */
    public function __construct(
        public PayrollJob $payrollJob
    ) {}

    /**
     * Execute the job.
     */
    public function handle(PaymentService $paymentService): void
    {
        try {
            $success = $paymentService->processPayrollJob($this->payrollJob);

            if ($success) {
                try {
                    // Reload PayrollJob completely fresh from database to avoid any stale eager load metadata
                    $freshPayrollJob = PayrollJob::query()
                        ->where('id', $this->payrollJob->id)
                        ->firstOrFail();

                    // Load only what we need for the email
                    $freshPayrollJob->load('payrollSchedule.business.owner');
                    $user = $freshPayrollJob->payrollSchedule?->business?->owner;

                    if ($user) {
                        try {
                            $emailService = app(EmailService::class);
                            // Pass the fresh instance to avoid any serialization issues
                            $emailService->send($user, new PayrollSuccessEmail($user, $freshPayrollJob), 'payroll_success');
                        } catch (\Exception $e) {
                            // Log error but don't fail the job - payment already succeeded
                            Log::error('Failed to send payroll success email', [
                                'payroll_job_id' => $freshPayrollJob->id,
                                'user_id' => $user->id,
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }

                    // Send payslip email to employee if they have an email address
                    $freshPayrollJob->load('employee');
                    $employee = $freshPayrollJob->employee;
                    if ($employee && $employee->email) {
                        try {
                            Mail::to($employee->email)->queue(new PayslipEmail($employee, $freshPayrollJob));
                            Log::info('Payslip email sent to employee', [
                                'employee_id' => $employee->id,
                                'employee_email' => $employee->email,
                                'payroll_job_id' => $freshPayrollJob->id,
                            ]);
                        } catch (\Exception $e) {
                            // Log error but don't fail the job
                            Log::error('Failed to send payslip email to employee', [
                                'employee_id' => $employee->id,
                                'employee_email' => $employee->email,
                                'payroll_job_id' => $freshPayrollJob->id,
                                'error' => $e->getMessage(),
                            ]);
                        }
                    } else {
                        Log::info('Skipping payslip email - employee has no email address', [
                            'employee_id' => $employee?->id,
                            'payroll_job_id' => $freshPayrollJob->id,
                        ]);
                    }
                } catch (\Exception $e) {
                    // Log error but don't fail the job - payment already succeeded
                    Log::error('Failed to send payroll success notifications', [
                        'payroll_job_id' => $this->payrollJob->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            } else {
                Log::warning('Payroll job failed', [
                    'payroll_job_id' => $this->payrollJob->id,
                    'attempt' => $this->attempts(),
                ]);

                // Check if this is a permanent failure that shouldn't be retried
                $errorMessage = 'Payroll processing failed';
                $freshJob = PayrollJob::find($this->payrollJob->id);
                if ($freshJob && $freshJob->error_message) {
                    $errorMessage = $freshJob->error_message;
                }

                $exception = new \Exception($errorMessage);

                // If this is a permanent failure, mark as failed immediately
                if (! $this->shouldRetry($exception)) {
                    if ($freshJob) {
                        $freshJob->updateStatus('failed', $errorMessage);
                    }

                    // Don't retry - job is permanently failed
                    return;
                }

                // Re-throw to trigger retry mechanism
                throw $exception;
            }
        } catch (\Exception $e) {
            // Cache error classification service instance
            static $errorClassification = null;
            if ($errorClassification === null) {
                $errorClassification = app(\App\Services\ErrorClassificationService::class);
            }

            // Cache classification results by exception message hash to avoid repeated classification
            $errorHash = md5(get_class($e).':'.$e->getMessage());
            static $classificationCache = [];

            if (! isset($classificationCache[$errorHash])) {
                $classificationCache[$errorHash] = [
                    'type' => $errorClassification->classify($e),
                    'category' => $errorClassification->getCategory($e),
                ];
            }

            $errorType = $classificationCache[$errorHash]['type'];
            $errorCategory = $classificationCache[$errorHash]['category'];

            Log::error('Payroll job exception', [
                'payroll_job_id' => $this->payrollJob->id,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'attempt' => $this->attempts(),
                'max_attempts' => $this->tries,
                'error_type' => $errorType,
                'error_category' => $errorCategory,
            ]);

            // Check if we should retry
            if (! $this->shouldRetry($e)) {
                // Permanent failure - mark as failed immediately
                $freshJob = PayrollJob::find($this->payrollJob->id);
                if ($freshJob) {
                    $freshJob->updateStatus('failed', $e->getMessage());
                }

                // Don't retry - job is permanently failed
                return;
            }

            // Check if we've exhausted retries
            if ($this->attempts() >= $this->tries) {
                // Max attempts reached - will be handled by failed() method
                throw $e;
            }

            // Re-throw to trigger retry mechanism with exponential backoff
            throw $e;
        }
    }

    /**
     * Calculate the number of seconds to wait before retrying the job.
     * Uses exponential backoff with jitter to prevent thundering herd.
     */
    public function backoff(): array
    {
        $baseDelays = [
            60,   // 1 minute
            300,  // 5 minutes
            900,  // 15 minutes
        ];

        // Add jitter (±10%) to each delay to prevent synchronized retries
        return array_map(function ($delay) {
            $jitter = (int) ($delay * 0.1 * (random_int(0, 20) - 10) / 10); // ±10% jitter

            return max(1, $delay + $jitter); // Ensure minimum 1 second
        }, $baseDelays);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        try {
            // Reload fresh to get latest status
            $payrollJob = PayrollJob::query()
                ->where('id', $this->payrollJob->id)
                ->first();

            if ($payrollJob) {
                // Only update if not already in a terminal state
                if (! in_array($payrollJob->status, ['succeeded', 'failed'])) {
                    $payrollJob->updateStatus('failed', $exception->getMessage());
                }

                // Mark as permanently failed (dead letter queue)
                $payrollJob->markAsPermanentlyFailed('max_retries_exceeded');
            }

            Log::error('Payroll job permanently failed', [
                'payroll_job_id' => $this->payrollJob->id,
                'exception' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            // Send failure email - reload fresh to avoid serialization issues
            $freshPayrollJob = PayrollJob::query()
                ->where('id', $this->payrollJob->id)
                ->firstOrFail();
            $freshPayrollJob->load('payrollSchedule.business.owner');
            $user = $freshPayrollJob->payrollSchedule?->business?->owner;
            if ($user) {
                try {
                    $emailService = app(EmailService::class);
                    $emailService->send($user, new PayrollFailedEmail($user, $freshPayrollJob), 'payroll_failed');
                } catch (\Exception $e) {
                    Log::error('Failed to send payroll failure email', [
                        'payroll_job_id' => $freshPayrollJob->id,
                        'user_id' => $user->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        } catch (\Exception $e) {
            // Log but don't throw - we're already in failed() method
            Log::error('Error in payroll job failed() method', [
                'payroll_job_id' => $this->payrollJob->id,
                'error' => $e->getMessage(),
                'original_exception' => $exception->getMessage(),
            ]);
        }
    }
}
