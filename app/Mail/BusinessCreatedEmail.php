<?php

namespace App\Mail;

use App\Models\Business;
use App\Models\BusinessTemplate;
use App\Models\User;
use App\Services\TemplateService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BusinessCreatedEmail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public User $user,
        public Business $business
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Business Created: '.$this->business->name,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        // Check for custom template
        $templateService = app(TemplateService::class);
        $customTemplate = $templateService->getBusinessTemplate(
            $this->business->id,
            BusinessTemplate::TYPE_EMAIL_BUSINESS_CREATED
        );

        if ($customTemplate && $customTemplate->compiled_html) {
            $html = $templateService->renderTemplate($customTemplate->compiled_html, [
                'subject' => 'Welcome to Swift Pay!',
                'business_name' => $this->business->name,
                'business_logo' => $this->business->logo ?? '',
                'app_logo' => asset('logo.svg'),
                'year' => date('Y'),
                'user_name' => $this->user->name,
                'dashboard_url' => route('dashboard'),
            ]);

            return new Content(
                view: 'emails.custom',
                with: ['html' => $html],
            );
        }

        return new Content(
            view: 'emails.business-created',
            with: [
                'user' => $this->user,
                'businessData' => $this->business, // For content display only
                'business' => null, // Explicitly null for email branding (app-related email from Swift Pay)
            ],
        );
    }
}
