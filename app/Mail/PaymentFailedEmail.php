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

class PaymentFailedEmail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public User $user,
        public PaymentJob $paymentJob
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        // Load business relationship
        $this->paymentJob->loadMissing('paymentSchedule.business');
        $business = $this->paymentJob->paymentSchedule->business;

        // Get business email and name, fallback to Swift Pay defaults
        $fromEmail = $business->email ?? config('mail.from.address');
        $fromName = $business->name ?? config('mail.from.name');

        return new Envelope(
            from: new Address($fromEmail, $fromName),
            subject: 'Payment Failed: '.number_format($this->paymentJob->amount, 2).' '.$this->paymentJob->currency,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $this->paymentJob->loadMissing(['paymentSchedule.business', 'receiver']);
        $business = $this->paymentJob->paymentSchedule->business;

        // Check for custom template
        $templateService = app(TemplateService::class);
        $customTemplate = $templateService->getBusinessTemplate(
            $business->id,
            BusinessTemplate::TYPE_EMAIL_PAYMENT_FAILED
        );

        if ($customTemplate && $customTemplate->compiled_html) {
            $html = $templateService->renderTemplate($customTemplate->compiled_html, [
                'subject' => 'Payment Failed',
                'business_name' => $business->name,
                'business_logo' => $business->logo ?? '',
                'year' => date('Y'),
                'amount' => number_format($this->paymentJob->amount, 2),
                'currency' => $this->paymentJob->currency,
                'receiver_name' => $this->paymentJob->receiver?->name ?? 'Unknown',
                'error_message' => $this->paymentJob->error_message ?? 'An error occurred',
                'retry_url' => route('payments.index'),
            ]);

            return new Content(
                view: 'emails.custom',
                with: ['html' => $html],
            );
        }

        return new Content(
            view: 'emails.payment-failed',
            with: [
                'user' => $this->user,
                'paymentJob' => $this->paymentJob,
                'business' => $business,
            ],
        );
    }
}
