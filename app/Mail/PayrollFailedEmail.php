<?php

namespace App\Mail;

use App\Models\BusinessTemplate;
use App\Models\PayrollJob;
use App\Models\User;
use App\Services\TemplateService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PayrollFailedEmail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public User $user,
        public PayrollJob $payrollJob
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        // Load business relationship
        $this->payrollJob->loadMissing(['payrollSchedule.business', 'employee.business']);
        $business = $this->payrollJob->payrollSchedule->business ?? $this->payrollJob->employee->business;

        // Get business email and name, fallback to Swift Pay defaults
        $fromEmail = $business->email ?? config('mail.from.address');
        $fromName = $business->name ?? config('mail.from.name');

        return new Envelope(
            from: new Address($fromEmail, $fromName),
            subject: 'Payroll Payment Failed: '.number_format($this->payrollJob->gross_salary, 2).' '.$this->payrollJob->currency,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $this->payrollJob->loadMissing(['payrollSchedule.business', 'employee.business']);
        $business = $this->payrollJob->payrollSchedule->business ?? $this->payrollJob->employee->business;

        // Check for custom template
        $templateService = app(TemplateService::class);
        $customTemplate = $templateService->getBusinessTemplate(
            $business->id,
            BusinessTemplate::TYPE_EMAIL_PAYROLL_FAILED
        );

        if ($customTemplate && $customTemplate->compiled_html) {
            $html = $templateService->renderTemplate($customTemplate->compiled_html, [
                'subject' => 'Payroll Processing Failed',
                'business_name' => $business->name,
                'business_logo' => $business->logo ?? '',
                'year' => date('Y'),
                'schedule_name' => $this->payrollJob->payrollSchedule?->name ?? 'Payroll',
                'error_message' => $this->payrollJob->error_message ?? 'An error occurred',
                'payroll_url' => route('payroll.index'),
            ]);

            return new Content(
                view: 'emails.custom',
                with: ['html' => $html],
            );
        }

        return new Content(
            view: 'emails.payroll-failed',
            with: [
                'user' => $this->user,
                'payrollJob' => $this->payrollJob,
                'business' => $business,
            ],
        );
    }
}
