<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'business_id',
        'phone_number',
        'verified_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'verified_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * Get the user that owns the session.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the business associated with this session.
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * Check if the session is verified.
     */
    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }

    /**
     * Check if the session has expired.
     */
    public function isExpired(): bool
    {
        if (! $this->expires_at) {
            return false;
        }

        return $this->expires_at->isPast();
    }

    /**
     * Check if the session is valid (verified and not expired).
     */
    public function isValid(): bool
    {
        return $this->isVerified() && ! $this->isExpired();
    }

    /**
     * Mark session as verified with 24-hour expiration.
     */
    public function markAsVerified(): void
    {
        $this->update([
            'verified_at' => now(),
            'expires_at' => now()->addHours(24),
        ]);
    }

    /**
     * Scope to find valid sessions.
     */
    public function scopeValid($query)
    {
        return $query->whereNotNull('verified_at')
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }

    /**
     * Scope to find session by phone number.
     */
    public function scopeByPhone($query, string $phoneNumber)
    {
        return $query->where('phone_number', $phoneNumber);
    }

    /**
     * Find valid session by phone number.
     */
    public static function findValidByPhone(string $phoneNumber): ?self
    {
        return static::byPhone($phoneNumber)->valid()->first();
    }
}
