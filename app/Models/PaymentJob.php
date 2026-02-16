<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentJob extends Model
{
    use HasFactory;

    protected $table = 'payment_jobs';

    protected $fillable = [
        'payment_schedule_id',
        'recipient_id',
        'amount',
        'currency',
        'status',
        'error_message',
        'processed_at',
        'transaction_id',
        'fee',
        'escrow_deposit_id',
        'fee_released_manually_at',
        'funds_returned_manually_at',
        'released_by',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'fee' => 'decimal:2',
            'processed_at' => 'datetime',
            'fee_released_manually_at' => 'datetime',
            'funds_returned_manually_at' => 'datetime',
        ];
    }

    public function paymentSchedule(): BelongsTo
    {
        return $this->belongsTo(PaymentSchedule::class);
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(Recipient::class);
    }

    public function escrowDeposit(): BelongsTo
    {
        return $this->belongsTo(EscrowDeposit::class);
    }

    public function releasedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by');
    }

    /**
     * Override update to implement optimistic locking
     */
    public function update(array $attributes = [], array $options = []): bool
    {
        if (! $this->exists) {
            return parent::update($attributes, $options);
        }

        // Optimistic locking: check version before update
        if (! isset($attributes['version'])) {
            $currentVersion = $this->version ?? 1;
            $attributes['version'] = $currentVersion + 1;

            // Use where clause to check version
            $query = $this->newQueryWithoutScopes()->where($this->getKeyName(), $this->getKey())
                ->where('version', $currentVersion);

            $updated = $query->update($attributes);

            if ($updated === 0) {
                throw new \RuntimeException('Optimistic locking failed: version mismatch. Record was modified by another process.');
            }

            // Refresh model
            $this->refresh();

            return true;
        }

        return parent::update($attributes, $options);
    }
}
