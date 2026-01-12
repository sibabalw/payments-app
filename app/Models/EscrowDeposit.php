<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EscrowDeposit extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'amount',
        'fee_amount',
        'authorized_amount',
        'currency',
        'status',
        'entry_method',
        'entered_by',
        'bank_reference',
        'deposited_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'fee_amount' => 'decimal:2',
            'authorized_amount' => 'decimal:2',
            'deposited_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function paymentJobs(): HasMany
    {
        return $this->hasMany(PaymentJob::class);
    }

    public function enteredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entered_by');
    }
}
