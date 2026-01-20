<?php

namespace App\Http\Controllers;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Services\AiChatService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ChatController extends Controller
{
    public function __construct(
        protected AiChatService $aiChatService
    ) {}

    /**
     * Display list of conversations for current business.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $businessId = $user->current_business_id;

        if (! $businessId) {
            return Inertia::render('chat/index', [
                'conversations' => [],
                'business' => null,
            ]);
        }

        $conversations = ChatConversation::where('user_id', $user->id)
            ->where('business_id', $businessId)
            ->with(['messages' => fn ($q) => $q->latest()->limit(1)])
            ->orderBy('updated_at', 'desc')
            ->get()
            ->map(fn ($conv) => [
                'id' => $conv->id,
                'title' => $conv->title ?? 'New Conversation',
                'updated_at' => $conv->updated_at->toISOString(),
                'last_message' => $conv->messages->first()?->content,
            ]);

        $business = $user->currentBusiness;

        return Inertia::render('chat/index', [
            'conversations' => $conversations,
            'business' => $business ? [
                'id' => $business->id,
                'name' => $business->name,
            ] : null,
        ]);
    }

    /**
     * Display a specific conversation with messages.
     */
    public function show(Request $request, ChatConversation $conversation): Response|RedirectResponse
    {
        $user = $request->user();

        // Verify ownership
        if ($conversation->user_id !== $user->id || $conversation->business_id !== $user->current_business_id) {
            return redirect()->route('chat.index')
                ->with('error', 'Conversation not found.');
        }

        $messages = $conversation->messages()
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(fn ($msg) => [
                'id' => $msg->id,
                'role' => $msg->role,
                'content' => $msg->content,
                'created_at' => $msg->created_at->toISOString(),
            ]);

        // Get all conversations for sidebar
        $conversations = ChatConversation::where('user_id', $user->id)
            ->where('business_id', $user->current_business_id)
            ->orderBy('updated_at', 'desc')
            ->get()
            ->map(fn ($conv) => [
                'id' => $conv->id,
                'title' => $conv->title ?? 'New Conversation',
                'updated_at' => $conv->updated_at->toISOString(),
            ]);

        $business = $user->currentBusiness;

        return Inertia::render('chat/conversation', [
            'conversation' => [
                'id' => $conversation->id,
                'title' => $conversation->title ?? 'New Conversation',
            ],
            'messages' => $messages,
            'conversations' => $conversations,
            'business' => $business ? [
                'id' => $business->id,
                'name' => $business->name,
            ] : null,
        ]);
    }

    /**
     * Create a new conversation.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $businessId = $user->current_business_id;

        if (! $businessId) {
            return redirect()->route('chat.index')
                ->with('error', 'Please select a business first.');
        }

        $conversation = ChatConversation::create([
            'user_id' => $user->id,
            'business_id' => $businessId,
            'title' => null, // Will be generated from first message
        ]);

        return redirect()->route('chat.show', $conversation);
    }

    /**
     * Send a message and get AI response.
     */
    public function sendMessage(Request $request, ChatConversation $conversation): RedirectResponse
    {
        $user = $request->user();

        // Verify ownership
        if ($conversation->user_id !== $user->id || $conversation->business_id !== $user->current_business_id) {
            return redirect()->route('chat.index')
                ->with('error', 'Conversation not found.');
        }

        $validated = $request->validate([
            'message' => 'required|string|max:5000',
        ]);

        // Save user message
        ChatMessage::create([
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => $validated['message'],
        ]);

        // Generate title from first message if not set
        if (! $conversation->title) {
            $conversation->update([
                'title' => str($validated['message'])->limit(50)->toString(),
            ]);
        }

        // Get AI response
        try {
            $aiResponse = $this->aiChatService->chat(
                businessId: $conversation->business_id,
                message: $validated['message'],
                conversationHistory: $conversation->messages()
                    ->orderBy('created_at', 'asc')
                    ->get()
                    ->map(fn ($msg) => [
                        'role' => $msg->role,
                        'content' => $msg->content,
                    ])
                    ->toArray()
            );

            // Save AI response
            ChatMessage::create([
                'conversation_id' => $conversation->id,
                'role' => 'assistant',
                'content' => $aiResponse['content'],
                'metadata' => $aiResponse['metadata'] ?? null,
            ]);
        } catch (\Exception $e) {
            // Save error message
            ChatMessage::create([
                'conversation_id' => $conversation->id,
                'role' => 'assistant',
                'content' => 'I apologize, but I encountered an error processing your request. Please try again later.',
                'metadata' => ['error' => $e->getMessage()],
            ]);
        }

        // Update conversation timestamp
        $conversation->touch();

        return redirect()->route('chat.show', $conversation);
    }

    /**
     * Delete a conversation.
     */
    public function destroy(Request $request, ChatConversation $conversation): RedirectResponse
    {
        $user = $request->user();

        // Verify ownership
        if ($conversation->user_id !== $user->id || $conversation->business_id !== $user->current_business_id) {
            return redirect()->route('chat.index')
                ->with('error', 'Conversation not found.');
        }

        $conversation->delete();

        return redirect()->route('chat.index')
            ->with('success', 'Conversation deleted.');
    }
}
