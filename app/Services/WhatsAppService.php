<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    protected string $phoneNumberId;

    protected string $accessToken;

    protected string $apiVersion = 'v18.0';

    public function __construct()
    {
        $this->phoneNumberId = config('services.whatsapp.phone_number_id', '');
        $this->accessToken = config('services.whatsapp.access_token', '');
    }

    /**
     * Send a text message to a WhatsApp number.
     */
    public function sendMessage(string $to, string $message): bool
    {
        if (! $this->isConfigured()) {
            Log::warning('WhatsApp service not configured');

            return false;
        }

        try {
            $response = Http::withToken($this->accessToken)
                ->post($this->getApiUrl('/messages'), [
                    'messaging_product' => 'whatsapp',
                    'recipient_type' => 'individual',
                    'to' => $this->normalizePhoneNumber($to),
                    'type' => 'text',
                    'text' => [
                        'preview_url' => false,
                        'body' => $message,
                    ],
                ]);

            if ($response->failed()) {
                Log::error('WhatsApp message send failed', [
                    'to' => $to,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return false;
            }

            Log::info('WhatsApp message sent', [
                'to' => $to,
                'message_id' => $response->json('messages.0.id'),
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('WhatsApp message send exception', [
                'to' => $to,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Send a template message.
     */
    public function sendTemplate(string $to, string $templateName, array $components = [], string $language = 'en'): bool
    {
        if (! $this->isConfigured()) {
            Log::warning('WhatsApp service not configured');

            return false;
        }

        try {
            $payload = [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $this->normalizePhoneNumber($to),
                'type' => 'template',
                'template' => [
                    'name' => $templateName,
                    'language' => [
                        'code' => $language,
                    ],
                ],
            ];

            if (! empty($components)) {
                $payload['template']['components'] = $components;
            }

            $response = Http::withToken($this->accessToken)
                ->post($this->getApiUrl('/messages'), $payload);

            if ($response->failed()) {
                Log::error('WhatsApp template send failed', [
                    'to' => $to,
                    'template' => $templateName,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return false;
            }

            Log::info('WhatsApp template sent', [
                'to' => $to,
                'template' => $templateName,
                'message_id' => $response->json('messages.0.id'),
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('WhatsApp template send exception', [
                'to' => $to,
                'template' => $templateName,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Mark a message as read.
     */
    public function markAsRead(string $messageId): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }

        try {
            $response = Http::withToken($this->accessToken)
                ->post($this->getApiUrl('/messages'), [
                    'messaging_product' => 'whatsapp',
                    'status' => 'read',
                    'message_id' => $messageId,
                ]);

            return $response->successful();
        } catch (\Exception $e) {
            Log::warning('Failed to mark WhatsApp message as read', [
                'message_id' => $messageId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Check if the service is properly configured.
     */
    public function isConfigured(): bool
    {
        return ! empty($this->phoneNumberId) && ! empty($this->accessToken);
    }

    /**
     * Get the API URL for a given endpoint.
     */
    protected function getApiUrl(string $endpoint): string
    {
        return "https://graph.facebook.com/{$this->apiVersion}/{$this->phoneNumberId}{$endpoint}";
    }

    /**
     * Normalize phone number to WhatsApp format (remove + and spaces).
     */
    protected function normalizePhoneNumber(string $phone): string
    {
        return preg_replace('/[^0-9]/', '', $phone);
    }
}
