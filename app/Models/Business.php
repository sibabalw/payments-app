<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Business extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'logo',
        'business_type',
        'status',
        'status_reason',
        'status_changed_at',
        'registration_number',
        'tax_id',
        'email',
        'phone',
        'website',
        'street_address',
        'city',
        'province',
        'postal_code',
        'country',
        'description',
        'contact_person_name',
    ];

    protected $casts = [
        'status_changed_at' => 'datetime',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'business_user')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function receivers(): HasMany
    {
        return $this->hasMany(Receiver::class);
    }

    public function paymentSchedules(): HasMany
    {
        return $this->hasMany(PaymentSchedule::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }


    public function escrowDeposits(): HasMany
    {
        return $this->hasMany(EscrowDeposit::class);
    }

    public function monthlyBillings(): HasMany
    {
        return $this->hasMany(MonthlyBilling::class);
    }

    /**
     * Check if business is active
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if business is banned
     */
    public function isBanned(): bool
    {
        return $this->status === 'banned';
    }

    /**
     * Check if business is suspended
     */
    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }

    /**
     * Check if business can perform actions (not banned or suspended)
     */
    public function canPerformActions(): bool
    {
        return $this->isActive();
    }

    /**
     * Scope to get only active businesses
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope to get banned businesses
     */
    public function scopeBanned($query)
    {
        return $query->where('status', 'banned');
    }

    /**
     * Scope to get suspended businesses
     */
    public function scopeSuspended($query)
    {
        return $query->where('status', 'suspended');
    }

    /**
     * Update business status (typically called by admin)
     */
    public function updateStatus(string $status, ?string $reason = null): void
    {
        if (!in_array($status, ['active', 'suspended', 'banned'])) {
            throw new \InvalidArgumentException("Invalid status: {$status}");
        }

        $this->update([
            'status' => $status,
            'status_reason' => $reason,
            'status_changed_at' => now(),
        ]);
    }
}
