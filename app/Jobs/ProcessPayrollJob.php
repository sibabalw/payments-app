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
                // Refresh to get updated status
                $this->payrollJob->refresh();

                // Send success email to business owner
                $user = $this->payrollJob->payrollSchedule->business->owner;
                $emailService = app(EmailService::class);
                $emailService->send($user, new PayrollSuccessEmail($user, $this->payrollJob), 'payroll_success');

                // Send payslip email to employee if they have an email address
                $employee = $this->payrollJob->employee;
                if ($employee && $employee->email) {
                    try {
                        Mail::to($employee->email)->queue(new PayslipEmail($employee, $this->payrollJob));
                        Log::info('Payslip email sent to employee', [
                            'employee_id' => $employee->id,
                            'employee_email' => $employee->email,
                            'payroll_job_id' => $this->payrollJob->id,
                        ]);
                    } catch (\Exception $e) {
                        // Log error but don't fail the job
                        Log::error('Failed to send payslip email to employee', [
                            'employee_id' => $employee->id,
                            'employee_email' => $employee->email,
                            'payroll_job_id' => $this->payrollJob->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                } else {
                    Log::info('Skipping payslip email - employee has no email address', [
                        'employee_id' => $employee?->id,
                        'payroll_job_id' => $this->payrollJob->id,
                    ]);
                }
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
        $emailService->send($user, new PayrollFailedEmail($user, $this->payrollJob), 'payroll_failed');
    }
}
