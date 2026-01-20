<?php

use App\Models\Business;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create([
        'onboarding_completed_at' => now(),
    ]);
    $this->business = Business::factory()->create([
        'user_id' => $this->user->id,
        'status' => 'active',
    ]);
    $this->user->businesses()->attach($this->business->id, ['role' => 'owner']);
    $this->user->update(['current_business_id' => $this->business->id]);
});

describe('Chat Index', function () {
    it('shows chat index page', function () {
        $response = $this->actingAs($this->user)
            ->get('/chat');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('chat/index')
            ->has('business')
            ->has('conversations')
        );
    });

    it('shows empty state when no business selected', function () {
        $this->user->update(['current_business_id' => null]);

        $response = $this->actingAs($this->user)
            ->get('/chat');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('chat/index')
            ->where('business', null)
        );
    });

    it('lists conversations for current business only', function () {
        // Create conversation for current business
        $conversation1 = ChatConversation::create([
            'user_id' => $this->user->id,
            'business_id' => $this->business->id,
            'title' => 'Business 1 Chat',
        ]);

        // Create another business and conversation
        $otherBusiness = Business::factory()->create(['user_id' => $this->user->id]);
        $conversation2 = ChatConversation::create([
            'user_id' => $this->user->id,
            'business_id' => $otherBusiness->id,
            'title' => 'Business 2 Chat',
        ]);

        $response = $this->actingAs($this->user)
            ->get('/chat');

        $response->assertInertia(fn ($page) => $page
            ->has('conversations', 1)
            ->where('conversations.0.title', 'Business 1 Chat')
        );
    });
});

describe('Create Conversation', function () {
    it('creates a new conversation', function () {
        $response = $this->actingAs($this->user)
            ->post('/chat');

        $response->assertRedirect();

        $this->assertDatabaseHas('chat_conversations', [
            'user_id' => $this->user->id,
            'business_id' => $this->business->id,
        ]);
    });

    it('fails without a selected business', function () {
        $this->user->update(['current_business_id' => null]);

        $response = $this->actingAs($this->user)
            ->post('/chat');

        $response->assertRedirect('/chat');
    });
});

describe('View Conversation', function () {
    it('shows conversation with messages', function () {
        $conversation = ChatConversation::create([
            'user_id' => $this->user->id,
            'business_id' => $this->business->id,
            'title' => 'Test Chat',
        ]);

        ChatMessage::create([
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => 'Hello AI',
        ]);

        ChatMessage::create([
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => 'Hello! How can I help?',
        ]);

        $response = $this->actingAs($this->user)
            ->get("/chat/{$conversation->id}");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('chat/conversation')
            ->has('messages', 2)
            ->where('conversation.title', 'Test Chat')
        );
    });

    it('denies access to other users conversations', function () {
        $otherUser = User::factory()->create();
        $conversation = ChatConversation::create([
            'user_id' => $otherUser->id,
            'business_id' => $this->business->id,
            'title' => 'Other User Chat',
        ]);

        $response = $this->actingAs($this->user)
            ->get("/chat/{$conversation->id}");

        $response->assertRedirect('/chat');
    });

    it('denies access to conversations from other businesses', function () {
        $otherBusiness = Business::factory()->create(['user_id' => $this->user->id]);
        $conversation = ChatConversation::create([
            'user_id' => $this->user->id,
            'business_id' => $otherBusiness->id,
            'title' => 'Other Business Chat',
        ]);

        $response = $this->actingAs($this->user)
            ->get("/chat/{$conversation->id}");

        $response->assertRedirect('/chat');
    });
});

describe('Delete Conversation', function () {
    it('deletes a conversation', function () {
        $conversation = ChatConversation::create([
            'user_id' => $this->user->id,
            'business_id' => $this->business->id,
            'title' => 'To Delete',
        ]);

        $response = $this->actingAs($this->user)
            ->delete("/chat/{$conversation->id}");

        $response->assertRedirect('/chat');
        $this->assertDatabaseMissing('chat_conversations', [
            'id' => $conversation->id,
        ]);
    });

    it('cannot delete other users conversations', function () {
        $otherUser = User::factory()->create();
        $conversation = ChatConversation::create([
            'user_id' => $otherUser->id,
            'business_id' => $this->business->id,
            'title' => 'Other User Chat',
        ]);

        $response = $this->actingAs($this->user)
            ->delete("/chat/{$conversation->id}");

        $response->assertRedirect('/chat');
        $this->assertDatabaseHas('chat_conversations', [
            'id' => $conversation->id,
        ]);
    });
});

describe('ChatConversation Model', function () {
    it('belongs to user and business', function () {
        $conversation = ChatConversation::create([
            'user_id' => $this->user->id,
            'business_id' => $this->business->id,
            'title' => 'Test',
        ]);

        expect($conversation->user->id)->toBe($this->user->id);
        expect($conversation->business->id)->toBe($this->business->id);
    });

    it('has many messages', function () {
        $conversation = ChatConversation::create([
            'user_id' => $this->user->id,
            'business_id' => $this->business->id,
        ]);

        ChatMessage::create([
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => 'Message 1',
        ]);

        ChatMessage::create([
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => 'Message 2',
        ]);

        expect($conversation->messages()->count())->toBe(2);
    });

    it('generates title from first message', function () {
        $conversation = ChatConversation::create([
            'user_id' => $this->user->id,
            'business_id' => $this->business->id,
        ]);

        ChatMessage::create([
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => 'How many employees do I have in my company?',
        ]);

        $conversation->generateTitleFromFirstMessage();

        expect($conversation->fresh()->title)->not->toBeNull();
    });
});

describe('ChatMessage Model', function () {
    it('belongs to conversation', function () {
        $conversation = ChatConversation::create([
            'user_id' => $this->user->id,
            'business_id' => $this->business->id,
        ]);

        $message = ChatMessage::create([
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => 'Test message',
        ]);

        expect($message->conversation->id)->toBe($conversation->id);
    });

    it('identifies user vs assistant messages', function () {
        $conversation = ChatConversation::create([
            'user_id' => $this->user->id,
            'business_id' => $this->business->id,
        ]);

        $userMessage = ChatMessage::create([
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => 'User message',
        ]);

        $assistantMessage = ChatMessage::create([
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => 'Assistant message',
        ]);

        expect($userMessage->isUserMessage())->toBeTrue();
        expect($userMessage->isAssistantMessage())->toBeFalse();
        expect($assistantMessage->isAssistantMessage())->toBeTrue();
        expect($assistantMessage->isUserMessage())->toBeFalse();
    });

    it('casts metadata to array', function () {
        $conversation = ChatConversation::create([
            'user_id' => $this->user->id,
            'business_id' => $this->business->id,
        ]);

        $message = ChatMessage::create([
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => 'Response',
            'metadata' => ['model' => 'gpt-4o', 'tokens' => 100],
        ]);

        expect($message->metadata)->toBeArray();
        expect($message->metadata['model'])->toBe('gpt-4o');
    });
});
