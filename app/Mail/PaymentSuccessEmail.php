<?php

namespace App\Mail;

use App\Models\BusinessTemplate;
use App\Models\PaymentJob;
use App\Models\User;
use App\Services\TemplateService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaymentSuccessEmail extends Mailable
{
    use Queueable, SerializesModels;

    public User $user;

    protected int $paymentJobId;

    /**
     * Create a new message instance.
     */
    public function __construct(
        User $user,
        PaymentJob $paymentJob
    ) {
        $this->user = $user;
        // Store only the ID - reload fresh to avoid any eager load metadata
        $this->paymentJobId = $paymentJob->id;
    }

    /**
     * Get the payment job, always loading it fresh.
     */
    protected function getPaymentJob(): PaymentJob
    {
        // Always reload completely fresh from database
        return PaymentJob::query()
            ->where('id', $this->paymentJobId)
            ->firstOrFail();
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $paymentJob = $this->getPaymentJob();
        $paymentJob->load('paymentSchedule.business');
        $business = $paymentJob->paymentSchedule?->business;

        // Get business email and name, fallback to Swift Pay defaults
        $fromEmail = $business?->email ?? config('mail.from.address');
        $fromName = $business?->name ?? config('mail.from.name');

        return new Envelope(
            from: new Address($fromEmail, $fromName),
            subject: 'Payment Successful: '.number_format($paymentJob->amount, 2).' '.$paymentJob->currency,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $paymentJob = $this->getPaymentJob();
        $paymentJob->load(['paymentSchedule.business', 'recipient']);
        $business = $paymentJob->paymentSchedule?->business;

        // Check for custom template
        $templateService = app(TemplateService::class);
        $customTemplate = $business ? $templateService->getBusinessTemplate(
            $business->id,
            BusinessTemplate::TYPE_EMAIL_PAYMENT_SUCCESS
        ) : null;

        if ($customTemplate && $customTemplate->compiled_html && $business) {
            // Convert logo to base64 data URI for email embedding
            $logoDataUri = $this->getLogoDataUri($business);

            $html = $templateService->renderTemplate($customTemplate->compiled_html, [
                'subject' => 'Payment Successful',
                'business_name' => $business->name ?? 'Unknown',
                'business_logo' => $logoDataUri,
                'year' => date('Y'),
                'amount' => number_format($paymentJob->amount, 2),
                'currency' => $paymentJob->currency,
                'receiver_name' => $paymentJob->recipient?->name ?? 'Unknown',
                'schedule_name' => $paymentJob->paymentSchedule?->name ?? 'Unknown',
                'transaction_id' => $paymentJob->transaction_id ?? 'N/A',
                'processed_at' => $paymentJob->processed_at?->format('F d, Y \a\t g:i A') ?? 'N/A',
                'payment_url' => route('payments.jobs'),
            ]);

            return new Content(
                view: 'emails.custom',
                with: ['html' => $html],
            );
        }

        return new Content(
            view: 'emails.payment-success',
            with: [
                'user' => $this->user,
                'paymentJob' => $paymentJob,
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
