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

class PayrollSuccessEmail extends Mailable
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
            subject: 'Payroll Payment Successful: '.number_format($this->payrollJob->net_salary, 2).' '.$this->payrollJob->currency,
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
            BusinessTemplate::TYPE_EMAIL_PAYROLL_SUCCESS
        );

        if ($customTemplate && $customTemplate->compiled_html) {
            $html = $templateService->renderTemplate($customTemplate->compiled_html, [
                'subject' => 'Payroll Processed Successfully',
                'business_name' => $business->name,
                'business_logo' => $business->logo ?? '',
                'year' => date('Y'),
                'total_amount' => number_format($this->payrollJob->net_salary, 2),
                'currency' => $this->payrollJob->currency,
                'employees_count' => '1', // Individual payroll job
                'pay_period' => $this->payrollJob->pay_period_start?->format('F Y') ?? now()->format('F Y'),
                'processed_at' => $this->payrollJob->processed_at?->format('F d, Y \a\t g:i A') ?? 'N/A',
                'payroll_url' => route('payroll.jobs'),
            ]);

            return new Content(
                view: 'emails.custom',
                with: ['html' => $html],
            );
        }

        return new Content(
            view: 'emails.payroll-success',
            with: [
                'user' => $this->user,
                'payrollJob' => $this->payrollJob,
                'business' => $business,
            ],
        );
    }
}
