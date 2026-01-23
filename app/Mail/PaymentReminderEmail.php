<?php

namespace App\Mail;

use App\Models\BusinessTemplate;
use App\Models\PaymentSchedule;
use App\Models\User;
use App\Services\TemplateService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaymentReminderEmail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public User $user,
        public PaymentSchedule $paymentSchedule
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        // Load business relationship
        $this->paymentSchedule->loadMissing('business');
        $business = $this->paymentSchedule->business;

        // Get business email and name, fallback to Swift Pay defaults
        $fromEmail = $business->email ?? config('mail.from.address');
        $fromName = $business->name ?? config('mail.from.name');

        return new Envelope(
            from: new Address($fromEmail, $fromName),
            subject: 'Payment Reminder: '.$this->paymentSchedule->name.' - '.$this->paymentSchedule->next_run_at->format('M d, Y H:i'),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $this->paymentSchedule->loadMissing('business');
        $business = $this->paymentSchedule->business;

        // Check for custom template
        $templateService = app(TemplateService::class);
        $customTemplate = $templateService->getBusinessTemplate(
            $business->id,
            BusinessTemplate::TYPE_EMAIL_PAYMENT_REMINDER
        );

        if ($customTemplate && $customTemplate->compiled_html) {
            // Convert logo to base64 data URI for email embedding
            $logoDataUri = $this->getLogoDataUri($business);

            $html = $templateService->renderTemplate($customTemplate->compiled_html, [
                'subject' => 'Payment Reminder',
                'business_name' => $business->name,
                'business_logo' => $logoDataUri,
                'year' => date('Y'),
                'schedule_name' => $this->paymentSchedule->name,
                'next_payment_date' => $this->paymentSchedule->next_run_at?->format('F d, Y') ?? 'N/A',
                'amount' => number_format($this->paymentSchedule->amount, 2),
                'currency' => $this->paymentSchedule->currency,
                'schedule_url' => route('payments.index'),
            ]);

            return new Content(
                view: 'emails.custom',
                with: ['html' => $html],
            );
        }

        return new Content(
            view: 'emails.payment-reminder',
            with: [
                'user' => $this->user,
                'paymentSchedule' => $this->paymentSchedule,
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
