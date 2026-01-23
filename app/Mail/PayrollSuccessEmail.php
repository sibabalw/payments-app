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

        // Get business email and name, fallback to Swift Pay defaults
        $fromEmail = $business->email ?? config('mail.from.address');
        $fromName = $business->name ?? config('mail.from.name');

        return new Envelope(
            from: new Address($fromEmail, $fromName),
            subject: 'Payroll Payment Successful: '.number_format($payrollJob->net_salary, 2).' '.$payrollJob->currency,
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
            BusinessTemplate::TYPE_EMAIL_PAYROLL_SUCCESS
        );

        if ($customTemplate && $customTemplate->compiled_html) {
            // Convert logo to base64 data URI for email embedding
            $logoDataUri = $this->getLogoDataUri($business);

            $html = $templateService->renderTemplate($customTemplate->compiled_html, [
                'subject' => 'Payroll Processed Successfully',
                'business_name' => $business->name,
                'business_logo' => $logoDataUri,
                'year' => date('Y'),
                'total_amount' => number_format($payrollJob->net_salary, 2),
                'currency' => $payrollJob->currency,
                'employees_count' => '1', // Individual payroll job
                'pay_period' => $payrollJob->pay_period_start?->format('F Y') ?? now()->format('F Y'),
                'processed_at' => $payrollJob->processed_at?->format('F d, Y \a\t g:i A') ?? 'N/A',
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
        if (! $business || ! $business->logo || trim($business->logo) === '') {
            \Illuminate\Support\Facades\Log::info('No logo to convert', [
                'business_id' => $business->id ?? null,
                'has_logo' => isset($business->logo),
                'logo_value' => $business->logo ?? null,
            ]);

            return '';
        }

        try {
            $logoPath = $business->logo;

            // Check if it's already a URL or data URI
            if (filter_var($logoPath, FILTER_VALIDATE_URL)) {
                \Illuminate\Support\Facades\Log::info('Logo is already a URL', ['logo_path' => $logoPath]);

                return $logoPath;
            }

            if (str_starts_with($logoPath, 'data:')) {
                \Illuminate\Support\Facades\Log::info('Logo is already a data URI');

                return $logoPath;
            }

            if (! \Illuminate\Support\Facades\Storage::disk('public')->exists($logoPath)) {
                \Illuminate\Support\Facades\Log::warning('Logo file does not exist', [
                    'logo_path' => $logoPath,
                    'business_id' => $business->id,
                ]);

                return '';
            }

            $logoContents = \Illuminate\Support\Facades\Storage::disk('public')->get($logoPath);
            $mimeType = \Illuminate\Support\Facades\Storage::disk('public')->mimeType($logoPath) ?: 'image/png';
            $base64 = base64_encode($logoContents);
            $dataUri = "data:{$mimeType};base64,{$base64}";

            \Illuminate\Support\Facades\Log::info('Logo converted to base64', [
                'mime_type' => $mimeType,
                'base64_length' => strlen($base64),
                'data_uri_length' => strlen($dataUri),
                'business_id' => $business->id,
            ]);

            return $dataUri;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to convert logo to base64', [
                'logo_path' => $business->logo ?? null,
                'business_id' => $business->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return '';
        }
    }
}
