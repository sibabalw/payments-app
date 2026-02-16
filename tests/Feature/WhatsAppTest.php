<?php

use App\Models\Business;
use App\Models\User;
use App\Models\WhatsAppSession;
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

describe('WhatsApp Webhook Verification', function () {
    it('verifies webhook with correct token', function () {
        config(['services.whatsapp.verify_token' => 'test-verify-token']);

        $response = $this->get('/api/whatsapp/webhook?'.http_build_query([
            'hub_mode' => 'subscribe',
            'hub_verify_token' => 'test-verify-token',
            'hub_challenge' => 'challenge-123',
        ]));

        $response->assertStatus(200);
        $response->assertSee('challenge-123');
    });

    it('rejects webhook with incorrect token', function () {
        config(['services.whatsapp.verify_token' => 'test-verify-token']);

        $response = $this->get('/api/whatsapp/webhook?'.http_build_query([
            'hub_mode' => 'subscribe',
            'hub_verify_token' => 'wrong-token',
            'hub_challenge' => 'challenge-123',
        ]));

        $response->assertStatus(403);
    });
});

describe('WhatsApp Webhook Handler', function () {
    it('acknowledges incoming webhook', function () {
        $payload = [
            'entry' => [
                [
                    'id' => '123',
                    'changes' => [
                        [
                            'field' => 'messages',
                            'value' => [
                                'messages' => [
                                    [
                                        'from' => '27821234567',
                                        'type' => 'text',
                                        'text' => ['body' => 'hello'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->postJson('/api/whatsapp/webhook', $payload);

        $response->assertStatus(200);
        $response->assertJson(['status' => 'ok']);
    });
});

describe('WhatsAppSession Model', function () {
    it('belongs to user and business', function () {
        $session = WhatsAppSession::create([
            'user_id' => $this->user->id,
            'business_id' => $this->business->id,
            'phone_number' => '27821234567',
            'verified_at' => now(),
            'expires_at' => now()->addHours(24),
        ]);

        expect($session->user->id)->toBe($this->user->id);
        expect($session->business->id)->toBe($this->business->id);
    });

    it('checks if session is valid', function () {
        $validSession = WhatsAppSession::create([
            'user_id' => $this->user->id,
            'business_id' => $this->business->id,
            'phone_number' => '27821234567',
            'verified_at' => now(),
            'expires_at' => now()->addHours(24),
        ]);

        $expiredSession = WhatsAppSession::create([
            'user_id' => $this->user->id,
            'business_id' => $this->business->id,
            'phone_number' => '27821234568',
            'verified_at' => now()->subHours(25),
            'expires_at' => now()->subHours(1),
        ]);

        $unverifiedSession = WhatsAppSession::create([
            'user_id' => $this->user->id,
            'business_id' => $this->business->id,
            'phone_number' => '27821234569',
            'verified_at' => null,
            'expires_at' => null,
        ]);

        expect($validSession->isValid())->toBeTrue();
        expect($expiredSession->isValid())->toBeFalse();
        expect($unverifiedSession->isValid())->toBeFalse();
    });

    it('finds valid session by phone', function () {
        WhatsAppSession::create([
            'user_id' => $this->user->id,
            'business_id' => $this->business->id,
            'phone_number' => '27821234567',
            'verified_at' => now(),
            'expires_at' => now()->addHours(24),
        ]);

        $found = WhatsAppSession::findValidByPhone('27821234567');
        $notFound = WhatsAppSession::findValidByPhone('27821234999');

        expect($found)->not->toBeNull();
        expect($notFound)->toBeNull();
    });

    it('marks session as verified', function () {
        $session = WhatsAppSession::create([
            'user_id' => $this->user->id,
            'business_id' => $this->business->id,
            'phone_number' => '27821234567',
        ]);

        expect($session->isVerified())->toBeFalse();

        $session->markAsVerified();
        $session->refresh();

        expect($session->isVerified())->toBeTrue();
        expect($session->expires_at)->not->toBeNull();
    });

    it('scopes valid sessions', function () {
        // Valid session
        WhatsAppSession::create([
            'user_id' => $this->user->id,
            'business_id' => $this->business->id,
            'phone_number' => '27821234567',
            'verified_at' => now(),
            'expires_at' => now()->addHours(24),
        ]);

        // Expired session
        WhatsAppSession::create([
            'user_id' => $this->user->id,
            'business_id' => $this->business->id,
            'phone_number' => '27821234568',
            'verified_at' => now()->subHours(25),
            'expires_at' => now()->subHours(1),
        ]);

        // Unverified session
        WhatsAppSession::create([
            'user_id' => $this->user->id,
            'business_id' => $this->business->id,
            'phone_number' => '27821234569',
        ]);

        $validSessions = WhatsAppSession::valid()->get();

        expect($validSessions)->toHaveCount(1);
        expect($validSessions->first()->phone_number)->toBe('27821234567');
    });
});

describe('AI Data API', function () {
    it('requires authentication for AI data endpoints', function () {
        $response = $this->getJson("/api/ai/business/{$this->business->id}/summary");

        $response->assertStatus(401);
    });

    it('returns business summary with valid API key', function () {
        config(['services.ai_mvp_server.api_key' => 'test-api-key']);

        $response = $this->withHeaders([
            'X-AI-Server-Key' => 'test-api-key',
        ])->getJson("/api/ai/business/{$this->business->id}/summary");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'id',
            'name',
            'business_type',
            'status',
        ]);
    });

    it('returns employees summary', function () {
        config(['services.ai_mvp_server.api_key' => 'test-api-key']);

        $response = $this->withHeaders([
            'X-AI-Server-Key' => 'test-api-key',
        ])->getJson("/api/ai/business/{$this->business->id}/employees/summary");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'total_count',
            'departments',
            'employment_types',
            'average_salary',
            'total_monthly_payroll',
        ]);
    });

    it('returns payments summary', function () {
        config(['services.ai_mvp_server.api_key' => 'test-api-key']);

        $response = $this->withHeaders([
            'X-AI-Server-Key' => 'test-api-key',
        ])->getJson("/api/ai/business/{$this->business->id}/payments/summary");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'total_schedules',
            'schedule_statuses',
            'upcoming_payments',
            'recent_jobs_30_days',
        ]);
    });

    it('returns escrow balance', function () {
        config(['services.ai_mvp_server.api_key' => 'test-api-key']);

        $response = $this->withHeaders([
            'X-AI-Server-Key' => 'test-api-key',
        ])->getJson("/api/ai/business/{$this->business->id}/escrow/balance");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'current_balance',
            'currency',
            'upcoming_obligations_7_days',
            'is_sufficient',
        ]);
    });

    it('returns compliance status', function () {
        config(['services.ai_mvp_server.api_key' => 'test-api-key']);

        $response = $this->withHeaders([
            'X-AI-Server-Key' => 'test-api-key',
        ])->getJson("/api/ai/business/{$this->business->id}/compliance/status");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'current_month',
            'current_tax_year',
            'ui19',
            'emp201',
            'irp5',
        ]);
    });

    it('returns full context', function () {
        config(['services.ai_mvp_server.api_key' => 'test-api-key']);

        $response = $this->withHeaders([
            'X-AI-Server-Key' => 'test-api-key',
        ])->getJson("/api/ai/business/{$this->business->id}/context");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'business',
            'employees',
            'payments',
            'payroll',
            'escrow',
            'compliance',
        ]);
    });
});
