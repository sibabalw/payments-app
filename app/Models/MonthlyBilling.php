<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MonthlyBilling extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'billing_month',
        'business_type',
        'subscription_fee',
        'total_deposit_fees',
        'status',
        'billed_at',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'subscription_fee' => 'decimal:2',
            'total_deposit_fees' => 'decimal:2',
            'billed_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function billingTransactions(): HasMany
    {
        return $this->hasMany(BillingTransaction::class);
    }
}
