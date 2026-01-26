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
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 60;

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

                // Always throw exception - if retries exhausted, it will trigger failed() method
                throw new \Exception('Payroll processing failed');
            }
        } catch (\Exception $e) {
            Log::error('Payroll job exception', [
                'payroll_job_id' => $this->payrollJob->id,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'attempt' => $this->attempts(),
                'max_attempts' => $this->tries,
            ]);

            // Re-throw to trigger retry mechanism or failed() method if retries exhausted
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
        try {
            // Reload fresh to get latest status
            $payrollJob = PayrollJob::query()
                ->where('id', $this->payrollJob->id)
                ->first();

            if ($payrollJob) {
                // Only update if not already in a terminal state
                if (! in_array($payrollJob->status, ['succeeded', 'failed'])) {
                    $payrollJob->update([
                        'status' => 'failed',
                        'error_message' => $exception->getMessage(),
                    ]);
                }
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
