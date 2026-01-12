<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Receiver extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'name',
        'email',
        'bank_account_details',
        'payout_method',
    ];

    protected function casts(): array
    {
        return [
            'bank_account_details' => 'array',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function paymentSchedules(): BelongsToMany
    {
        return $this->belongsToMany(PaymentSchedule::class, 'payment_schedule_receiver')
            ->withTimestamps();
    }

    public function paymentJobs(): HasMany
    {
        return $this->hasMany(PaymentJob::class);
    }
}
