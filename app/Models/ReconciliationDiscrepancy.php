<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReconciliationDiscrepancy extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'discrepancy_type',
        'stored_balance',
        'calculated_balance',
        'ledger_balance',
        'difference',
        'status',
        'approved_by',
        'approved_at',
        'resolution_notes',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'stored_balance' => 'decimal:2',
            'calculated_balance' => 'decimal:2',
            'ledger_balance' => 'decimal:2',
            'difference' => 'decimal:2',
            'approved_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Approve discrepancy for resolution
     */
    public function approve(User $user, ?string $notes = null): void
    {
        $this->update([
            'status' => 'approved',
            'approved_by' => $user->id,
            'approved_at' => now(),
            'resolution_notes' => $notes,
        ]);
    }

    /**
     * Mark as compensated (compensating transaction created)
     */
    public function markAsCompensated(): void
    {
        $this->update(['status' => 'compensated']);
    }

    /**
     * Mark as resolved
     */
    public function markAsResolved(): void
    {
        $this->update(['status' => 'resolved']);
    }
}
