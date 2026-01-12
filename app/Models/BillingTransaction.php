<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillingTransaction extends Model
{
    protected $fillable = [
        'business_id',
        'monthly_billing_id',
        'type',
        'amount',
        'currency',
        'description',
        'status',
        'bank_reference',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'processed_at' => 'datetime',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function monthlyBilling(): BelongsTo
    {
        return $this->belongsTo(MonthlyBilling::class);
    }
}
