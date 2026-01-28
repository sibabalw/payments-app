<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BalanceSnapshot extends Model
{
    use HasFactory;

    protected $fillable = [
        'account_type',
        'business_id',
        'snapshot_date',
        'balance_minor_units',
        'sequence_number',
        'checksum',
        'entry_count',
    ];

    protected function casts(): array
    {
        return [
            'snapshot_date' => 'date',
            'balance_minor_units' => 'integer',
            'sequence_number' => 'integer',
            'entry_count' => 'integer',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * Get balance as decimal
     */
    public function getBalanceAttribute(): float
    {
        return $this->balance_minor_units / 100.0; // Assuming ZAR (cents)
    }
}
