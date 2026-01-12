<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'type',
        'name',
        'frequency',
        'amount',
        'currency',
        'status',
        'schedule_type',
        'next_run_at',
        'last_run_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'next_run_at' => 'datetime',
            'last_run_at' => 'datetime',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function receivers(): BelongsToMany
    {
        return $this->belongsToMany(Receiver::class, 'payment_schedule_receiver')
            ->withTimestamps();
    }

    public function paymentJobs(): HasMany
    {
        return $this->hasMany(PaymentJob::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopePaused(Builder $query): Builder
    {
        return $query->where('status', 'paused');
    }

    public function scopeDue(Builder $query): Builder
    {
        return $query->where('status', 'active')
            ->where('next_run_at', '<=', now());
    }

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    public function isOneTime(): bool
    {
        return $this->schedule_type === 'one_time';
    }

    public function isRecurring(): bool
    {
        return $this->schedule_type === 'recurring';
    }

    public function scopeOneTime(Builder $query): Builder
    {
        return $query->where('schedule_type', 'one_time');
    }

    public function scopeRecurring(Builder $query): Builder
    {
        return $query->where('schedule_type', 'recurring');
    }
}
