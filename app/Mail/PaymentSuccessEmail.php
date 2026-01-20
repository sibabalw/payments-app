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
            subject: 'Payment Successful: '.number_format($this->paymentJob->amount, 2).' '.$this->paymentJob->currency,
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
            BusinessTemplate::TYPE_EMAIL_PAYMENT_SUCCESS
        );

        if ($customTemplate && $customTemplate->compiled_html) {
            $html = $templateService->renderTemplate($customTemplate->compiled_html, [
                'subject' => 'Payment Successful',
                'business_name' => $business->name,
                'business_logo' => $business->logo ?? '',
                'year' => date('Y'),
                'amount' => number_format($this->paymentJob->amount, 2),
                'currency' => $this->paymentJob->currency,
                'receiver_name' => $this->paymentJob->receiver?->name ?? 'Unknown',
                'schedule_name' => $this->paymentJob->paymentSchedule?->name ?? 'Unknown',
                'transaction_id' => $this->paymentJob->transaction_id ?? 'N/A',
                'processed_at' => $this->paymentJob->processed_at?->format('F d, Y \a\t g:i A') ?? 'N/A',
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
                'paymentJob' => $this->paymentJob,
                'business' => $business,
            ],
        );
    }
}
