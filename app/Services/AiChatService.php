<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiChatService
{
    protected string $mvpServerUrl;

    protected string $apiKey;

    protected int $timeout;

    public function __construct()
    {
        $this->mvpServerUrl = config('services.ai_mvp_server.url') ?? 'http://localhost:3001';
        $this->apiKey = config('services.ai_mvp_server.api_key') ?? '';
        $this->timeout = (int) (config('services.ai_mvp_server.timeout') ?? 60);
    }

    /**
     * Send a chat message to the AI MVP server and get a response.
     *
     * @param  int  $businessId  The business ID for context
     * @param  string  $message  The user's message
     * @param  array  $conversationHistory  Previous messages in the conversation
     * @return array{content: string, metadata: array|null}
     *
     * @throws \Exception
     */
    public function chat(int $businessId, string $message, array $conversationHistory = []): array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'X-AI-Server-Key' => $this->apiKey,
                    'Content-Type' => 'application/json',
                ])
                ->post("{$this->mvpServerUrl}/api/chat", [
                    'business_id' => $businessId,
                    'message' => $message,
                    'conversation_history' => $conversationHistory,
                ]);

            if ($response->failed()) {
                Log::error('AI MVP Server request failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'business_id' => $businessId,
                ]);

                throw new \Exception('Failed to get AI response: '.$response->status());
            }

            $data = $response->json();

            return [
                'content' => $data['content'] ?? 'I could not generate a response.',
                'metadata' => $data['metadata'] ?? null,
            ];
        } catch (ConnectionException $e) {
            Log::error('AI MVP Server connection failed', [
                'error' => $e->getMessage(),
                'business_id' => $businessId,
            ]);

            throw new \Exception('AI service is temporarily unavailable. Please try again later.');
        }
    }

    /**
     * Check if the AI MVP server is healthy.
     */
    public function healthCheck(): bool
    {
        try {
            $response = Http::timeout(5)
                ->get("{$this->mvpServerUrl}/health");

            return $response->successful();
        } catch (\Exception $e) {
            Log::warning('AI MVP Server health check failed', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get available AI capabilities.
     */
    public function getCapabilities(): array
    {
        return [
            'can_query' => [
                'business_info' => 'Business name, type, status, location',
                'employees' => 'Employee counts, departments, average salary',
                'payments' => 'Payment schedules, upcoming payments, recent payment history',
                'payroll' => 'Payroll schedules, upcoming payroll, recent payroll history',
                'escrow' => 'Current balance, upcoming obligations',
                'compliance' => 'UI-19, EMP201, IRP5 status',
            ],
            'cannot_do' => [
                'modify_data' => 'Cannot create, update, or delete any records',
                'access_sensitive' => 'Cannot access bank details, passwords, ID numbers',
                'execute_sql' => 'Cannot run database queries directly',
                'perform_actions' => 'Cannot trigger payments, send emails, etc.',
            ],
        ];
    }
}
