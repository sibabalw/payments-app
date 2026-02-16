<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class TransactionReversal extends Model
{
    protected $fillable = [
        'reversible_type',
        'reversible_id',
        'reversal_type',
        'reason',
        'status',
        'reversed_by',
        'metadata',
        'reversed_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'reversed_at' => 'datetime',
        ];
    }

    public function reversible(): MorphTo
    {
        return $this->morphTo();
    }

    public function reversedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by');
    }
}
