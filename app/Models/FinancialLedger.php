<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class FinancialLedger extends Model
{
    use HasFactory;

    protected $table = 'financial_ledger';

    protected $fillable = [
        'correlation_id',
        'transaction_type',
        'account_type',
        'business_id',
        'reference_type',
        'reference_id',
        'amount',
        'amount_minor_units',
        'currency',
        'description',
        'metadata',
        'reversal_of_id',
        'reversed_by_id',
        'user_id',
        'effective_at',
        'posting_state',
        'posted_at',
        'sequence_number',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'amount_minor_units' => 'integer',
            'metadata' => 'array',
            'effective_at' => 'datetime',
            'posted_at' => 'datetime',
        ];
    }

    /**
     * Posting states
     */
    public const POSTING_PENDING = 'PENDING';

    public const POSTING_POSTED = 'POSTED';

    public const POSTING_REVERSED = 'REVERSED';

    /**
     * Get amount in minor units (cents for ZAR)
     */
    public function getAmountMinorUnitsAttribute(): int
    {
        if (isset($this->attributes['amount_minor_units'])) {
            return (int) $this->attributes['amount_minor_units'];
        }

        // Fallback: convert from decimal amount
        return (int) round($this->amount * 100);
    }

    /**
     * Set amount in minor units and update decimal amount
     */
    public function setAmountMinorUnitsAttribute(int $value): void
    {
        $this->attributes['amount_minor_units'] = $value;
        $this->attributes['amount'] = $value / 100.0;
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reversedBy(): BelongsTo
    {
        return $this->belongsTo(FinancialLedger::class, 'reversed_by_id');
    }

    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(FinancialLedger::class, 'reversal_of_id');
    }

    public function reference(): MorphTo
    {
        return $this->morphTo('reference');
    }

    /**
     * Check if this entry is a debit
     */
    public function isDebit(): bool
    {
        return $this->transaction_type === 'DEBIT';
    }

    /**
     * Check if this entry is a credit
     */
    public function isCredit(): bool
    {
        return $this->transaction_type === 'CREDIT';
    }

    /**
     * Check if this entry is a reversal
     */
    public function isReversal(): bool
    {
        return $this->reversal_of_id !== null;
    }

    /**
     * Check if entry is posted
     */
    public function isPosted(): bool
    {
        return $this->posting_state === self::POSTING_POSTED;
    }

    /**
     * Check if entry is pending
     */
    public function isPending(): bool
    {
        return $this->posting_state === self::POSTING_PENDING;
    }

    /**
     * Mark entry as posted
     */
    public function markAsPosted(): void
    {
        $this->update([
            'posting_state' => self::POSTING_POSTED,
            'posted_at' => now(),
        ]);
    }
}
