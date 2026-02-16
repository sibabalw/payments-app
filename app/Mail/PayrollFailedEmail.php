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

    public User $user;

    protected int $payrollJobId;

    /**
     * Create a new message instance.
     */
    public function __construct(
        User $user,
        PayrollJob $payrollJob
    ) {
        $this->user = $user;
        // Store only the ID - reload fresh to avoid any eager load metadata
        $this->payrollJobId = $payrollJob->id;
    }

    /**
     * Get the payroll job, always loading it fresh.
     */
    protected function getPayrollJob(): PayrollJob
    {
        // Always reload completely fresh from database
        return PayrollJob::query()
            ->where('id', $this->payrollJobId)
            ->firstOrFail();
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $payrollJob = $this->getPayrollJob();
        $payrollJob->load('payrollSchedule.business', 'employee.business');
        $business = $payrollJob->payrollSchedule->business ?? $payrollJob->employee->business;

        // Get business email and name, fallback to SwiftPay defaults
        $fromEmail = $business->email ?? config('mail.from.address');
        $fromName = $business->name ?? config('mail.from.name');

        return new Envelope(
            from: new Address($fromEmail, $fromName),
            subject: 'Payroll Payment Failed: '.number_format($payrollJob->gross_salary, 2).' '.$payrollJob->currency,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $payrollJob = $this->getPayrollJob();
        $payrollJob->load('payrollSchedule.business', 'employee.business');
        $business = $payrollJob->payrollSchedule->business ?? $payrollJob->employee->business;

        // Check for custom template
        $templateService = app(TemplateService::class);
        $customTemplate = $templateService->getBusinessTemplate(
            $business->id,
            BusinessTemplate::TYPE_EMAIL_PAYROLL_FAILED
        );

        if ($customTemplate && $customTemplate->compiled_html) {
            // Convert logo to base64 data URI for email embedding
            $logoDataUri = $this->getLogoDataUri($business);

            $html = $templateService->renderTemplate($customTemplate->compiled_html, [
                'subject' => 'Payroll Processing Failed',
                'business_name' => $business->name,
                'business_logo' => $logoDataUri,
                'year' => date('Y'),
                'schedule_name' => $payrollJob->payrollSchedule?->name ?? 'Payroll',
                'error_message' => $payrollJob->error_message ?? 'An error occurred',
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
                'payrollJob' => $payrollJob,
                'business' => $business,
            ],
        );
    }

    /**
     * Convert business logo to base64 data URI for email embedding.
     */
    protected function getLogoDataUri($business): string
    {
        if (! $business || ! $business->logo) {
            return '';
        }

        try {
            $logoPath = $business->logo;
            if (\Illuminate\Support\Facades\Storage::disk('public')->exists($logoPath)) {
                $logoContents = \Illuminate\Support\Facades\Storage::disk('public')->get($logoPath);
                $mimeType = \Illuminate\Support\Facades\Storage::disk('public')->mimeType($logoPath);
                $base64 = base64_encode($logoContents);

                return "data:{$mimeType};base64,{$base64}";
            }
        } catch (\Exception $e) {
            // Return empty string if logo can't be loaded
        }

        return '';
    }
}
