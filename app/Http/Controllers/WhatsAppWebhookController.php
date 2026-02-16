<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\WhatsAppSession;
use App\Services\AiChatService;
use App\Services\WhatsAppOtpService;
use App\Services\WhatsAppService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class WhatsAppWebhookController extends Controller
{
    public function __construct(
        protected WhatsAppService $whatsAppService,
        protected WhatsAppOtpService $otpService,
        protected AiChatService $aiChatService
    ) {}

    /**
     * Verify webhook (GET request from Meta).
     */
    public function verify(Request $request): Response
    {
        $mode = $request->query('hub_mode');
        $token = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        $verifyToken = config('services.whatsapp.verify_token');

        if ($mode === 'subscribe' && $token === $verifyToken) {
            Log::info('WhatsApp webhook verified');

            return response($challenge, 200);
        }

        Log::warning('WhatsApp webhook verification failed', [
            'mode' => $mode,
            'token_match' => $token === $verifyToken,
        ]);

        return response('Forbidden', 403);
    }

    /**
     * Handle incoming webhook events (POST request from Meta).
     */
    public function handle(Request $request): JsonResponse
    {
        $data = $request->all();

        Log::info('WhatsApp webhook received', ['data' => $data]);

        // Verify webhook signature (optional but recommended)
        if (! $this->verifySignature($request)) {
            Log::warning('WhatsApp webhook signature verification failed');

            return response()->json(['status' => 'error'], 401);
        }

        // Process the webhook data
        try {
            $this->processWebhook($data);
        } catch (\Exception $e) {
            Log::error('WhatsApp webhook processing error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        // Always return 200 to acknowledge receipt
        return response()->json(['status' => 'ok']);
    }

    /**
     * Process the webhook data.
     */
    protected function processWebhook(array $data): void
    {
        // Check if this is a messages webhook
        $entry = $data['entry'][0] ?? null;
        if (! $entry) {
            return;
        }

        $changes = $entry['changes'][0] ?? null;
        if (! $changes || $changes['field'] !== 'messages') {
            return;
        }

        $value = $changes['value'] ?? null;
        if (! $value) {
            return;
        }

        // Get message data
        $messages = $value['messages'] ?? [];
        foreach ($messages as $message) {
            $this->handleMessage($message, $value);
        }
    }

    /**
     * Handle an individual message.
     */
    protected function handleMessage(array $message, array $value): void
    {
        $phoneNumber = $message['from'] ?? null;
        $messageType = $message['type'] ?? null;
        $messageText = $message['text']['body'] ?? null;

        if (! $phoneNumber || $messageType !== 'text' || ! $messageText) {
            return;
        }

        Log::info('Processing WhatsApp message', [
            'phone' => $phoneNumber,
            'text' => $messageText,
        ]);

        // Check for valid session
        $session = WhatsAppSession::findValidByPhone($phoneNumber);

        // Handle login flow
        $lowerText = strtolower(trim($messageText));

        if ($lowerText === 'login' || $lowerText === 'start') {
            $this->handleLoginRequest($phoneNumber);

            return;
        }

        // Check if this is an OTP verification
        if (preg_match('/^\d{6}$/', $messageText)) {
            $this->handleOtpVerification($phoneNumber, $messageText);

            return;
        }

        // Check if user has a valid session
        if (! $session || ! $session->isValid()) {
            $this->whatsAppService->sendMessage(
                $phoneNumber,
                "You're not logged in. Send 'login' followed by your email address to start.\n\nExample: login john@example.com"
            );

            return;
        }

        // Process AI query
        $this->handleAiQuery($session, $phoneNumber, $messageText);
    }

    /**
     * Handle login request.
     */
    protected function handleLoginRequest(string $phoneNumber): void
    {
        $this->whatsAppService->sendMessage(
            $phoneNumber,
            "Welcome to SwiftPay AI Assistant!\n\nPlease enter your email address to receive a verification code.\n\nExample: john@example.com"
        );

        // Store phone in pending state (we'll match email in next message)
        cache()->put("whatsapp_pending_email:{$phoneNumber}", true, now()->addMinutes(10));
    }

    /**
     * Handle email submission for login.
     */
    protected function handleEmailSubmission(string $phoneNumber, string $email): void
    {
        // Find user by email
        $user = User::where('email', strtolower($email))->first();

        if (! $user) {
            $this->whatsAppService->sendMessage(
                $phoneNumber,
                "We couldn't find an account with that email address. Please make sure you're using the email associated with your SwiftPay account."
            );

            return;
        }

        // Generate and send OTP
        try {
            $this->otpService->generateAndSendOtp($phoneNumber, $email);

            $this->whatsAppService->sendMessage(
                $phoneNumber,
                "We've sent a 6-digit verification code to {$email}.\n\nPlease enter the code here to complete login."
            );
        } catch (\Exception $e) {
            Log::error('Failed to send WhatsApp OTP', [
                'phone' => $phoneNumber,
                'error' => $e->getMessage(),
            ]);

            $this->whatsAppService->sendMessage(
                $phoneNumber,
                'Sorry, we encountered an error sending the verification code. Please try again later.'
            );
        }
    }

    /**
     * Handle OTP verification.
     */
    protected function handleOtpVerification(string $phoneNumber, string $otp): void
    {
        $result = $this->otpService->verifyOtp($phoneNumber, $otp);

        if (! $result) {
            $this->whatsAppService->sendMessage(
                $phoneNumber,
                "Invalid or expired verification code. Please try again or send 'login' to start over."
            );

            return;
        }

        $session = WhatsAppSession::findValidByPhone($phoneNumber);
        $businessName = $session?->business?->name ?? 'your business';

        $this->whatsAppService->sendMessage(
            $phoneNumber,
            "You're now logged in to {$businessName}!\n\nYou can ask me questions about your business data. For example:\n- How many employees do I have?\n- What's my escrow balance?\n- Show me upcoming payments\n\nSend 'logout' to end your session."
        );
    }

    /**
     * Handle AI query from authenticated user.
     */
    protected function handleAiQuery(WhatsAppSession $session, string $phoneNumber, string $message): void
    {
        // Handle logout
        if (strtolower(trim($message)) === 'logout') {
            $session->update(['expires_at' => now()]);

            $this->whatsAppService->sendMessage(
                $phoneNumber,
                "You've been logged out. Send 'login' to start a new session."
            );

            return;
        }

        // Send typing indicator (if supported)
        // Note: WhatsApp Cloud API doesn't have a direct typing indicator

        try {
            $response = $this->aiChatService->chat(
                businessId: $session->business_id,
                message: $message,
                conversationHistory: [] // WhatsApp doesn't maintain conversation history
            );

            $this->whatsAppService->sendMessage($phoneNumber, $response['content']);
        } catch (\Exception $e) {
            Log::error('AI query failed for WhatsApp', [
                'phone' => $phoneNumber,
                'error' => $e->getMessage(),
            ]);

            $this->whatsAppService->sendMessage(
                $phoneNumber,
                'Sorry, I encountered an error processing your request. Please try again later.'
            );
        }
    }

    /**
     * Verify webhook signature from Meta.
     */
    protected function verifySignature(Request $request): bool
    {
        $signature = $request->header('X-Hub-Signature-256');
        $secret = config('services.whatsapp.webhook_secret');

        if (! $signature || ! $secret) {
            // Skip verification if not configured
            return true;
        }

        $expectedSignature = 'sha256='.hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($expectedSignature, $signature);
    }
}
