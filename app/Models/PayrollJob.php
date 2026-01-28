<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollJob extends Model
{
    use HasFactory;

    protected $fillable = [
        'payroll_schedule_id',
        'employee_id',
        'gross_salary',
        'paye_amount',
        'uif_amount',
        'sdl_amount',
        'adjustments',
        'net_salary',
        'currency',
        'status',
        'error_message',
        'processed_at',
        'permanently_failed_at',
        'failed_reason',
        'transaction_id',
        'fee',
        'escrow_deposit_id',
        'fee_released_manually_at',
        'funds_returned_manually_at',
        'released_by',
        'pay_period_start',
        'pay_period_end',
        'calculation_hash',
        'calculation_version',
        'adjustment_inputs',
        'calculation_snapshot',
        'employee_snapshot',
        'version',
    ];

    /**
     * Immutable calculation fields that cannot be updated after creation
     */
    protected array $immutableFields = [
        'payroll_schedule_id',
        'employee_id',
        'gross_salary',
        'paye_amount',
        'uif_amount',
        'sdl_amount',
        'adjustments',
        'net_salary',
        'currency',
        'pay_period_start',
        'pay_period_end',
        'calculation_hash',
        'calculation_version',
        'adjustment_inputs',
        'calculation_snapshot',
        'employee_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'gross_salary' => 'decimal:2',
            'paye_amount' => 'decimal:2',
            'uif_amount' => 'decimal:2',
            'sdl_amount' => 'decimal:2',
            'adjustments' => 'array',
            'net_salary' => 'decimal:2',
            'fee' => 'decimal:2',
            'processed_at' => 'datetime',
            'permanently_failed_at' => 'datetime',
            'fee_released_manually_at' => 'datetime',
            'funds_returned_manually_at' => 'datetime',
            'pay_period_start' => 'date',
            'pay_period_end' => 'date',
            'calculation_snapshot' => 'array',
            'employee_snapshot' => 'array',
            'adjustment_inputs' => 'array',
            'calculation_version' => 'integer',
        ];
    }

    public function payrollSchedule(): BelongsTo
    {
        return $this->belongsTo(PayrollSchedule::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
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
     * Override update to prevent modification of immutable calculation fields and implement optimistic locking
     *
     * @return bool
     *
     * @throws \RuntimeException
     */
    public function update(array $attributes = [], array $options = [])
    {
        // If this is a new model, allow all updates
        if (! $this->exists) {
            return parent::update($attributes, $options);
        }

        // Check if trying to update immutable fields
        $immutableChanges = [];
        foreach ($this->immutableFields as $field) {
            if (array_key_exists($field, $attributes) && $this->getOriginal($field) !== $attributes[$field]) {
                $immutableChanges[] = $field;
            }
        }

        if (! empty($immutableChanges)) {
            throw new \RuntimeException(
                'Cannot update immutable calculation fields after payroll job creation: '.implode(', ', $immutableChanges)
            );
        }

        // Optimistic locking: check version before update
        if (! isset($attributes['version'])) {
            $currentVersion = $this->version ?? 1;
            $attributes['version'] = $currentVersion + 1;

            // Add version check to where clause
            $options['where'] = array_merge($options['where'] ?? [], [
                'version' => $currentVersion,
            ]);
        }

        // Allow updates to mutable fields (status, error_message, processed_at, etc.)
        $updated = parent::update($attributes, $options);

        // Check if update failed due to version mismatch
        if (! $updated && isset($options['where']['version'])) {
            throw new \RuntimeException('Optimistic locking failed: version mismatch. Record was modified by another process.');
        }

        return $updated;
    }

    /**
     * Valid status transitions for payroll jobs
     */
    protected array $validTransitions = [
        'pending' => ['processing', 'failed'],
        'processing' => ['succeeded', 'failed'],
        'succeeded' => [], // Terminal state - cannot transition from succeeded
        'failed' => ['pending'], // Can retry failed jobs by resetting to pending
    ];

    /**
     * Check if a status transition is valid
     */
    public function isValidTransition(string $fromStatus, string $toStatus): bool
    {
        // Same status is always valid (no-op)
        if ($fromStatus === $toStatus) {
            return true;
        }

        // Check if transition is in allowed list
        $allowedTransitions = $this->validTransitions[$fromStatus] ?? [];

        return in_array($toStatus, $allowedTransitions, true);
    }

    /**
     * Update only the status field (safe method for status transitions)
     *
     * @throws \RuntimeException
     */
    public function updateStatus(string $status, ?string $errorMessage = null): bool
    {
        // Validate status transition
        $currentStatus = $this->status ?? 'pending';
        if (! $this->isValidTransition($currentStatus, $status)) {
            throw new \RuntimeException(
                "Invalid status transition from '{$currentStatus}' to '{$status}'. ".
                'Allowed transitions: '.implode(', ', $this->validTransitions[$currentStatus] ?? [])
            );
        }

        $attributes = ['status' => $status];

        if ($errorMessage !== null) {
            $attributes['error_message'] = $errorMessage;
        }

        if ($status === 'succeeded' || $status === 'failed') {
            $attributes['processed_at'] = now();
        }

        return parent::update($attributes);
    }

    public function getTaxBreakdownAttribute(): array
    {
        // Calculate adjustments total (deductions only, not additions)
        $adjustmentsTotal = 0;
        if (is_array($this->adjustments)) {
            $adjustmentsTotal = collect($this->adjustments)
                ->filter(fn ($adj) => ($adj['adjustment_type'] ?? 'deduction') === 'deduction')
                ->sum('amount');
        }

        return [
            'gross' => $this->gross_salary,
            'paye' => $this->paye_amount,
            'uif' => $this->uif_amount,
            'sdl' => $this->sdl_amount,
            'net' => $this->net_salary,
            'total_deductions' => $this->paye_amount + $this->uif_amount + $adjustmentsTotal,
            // SDL is NOT included in total_deductions as it's an employer cost, not deducted from employee
        ];
    }

    /**
     * Scope to get permanently failed jobs (dead letter queue)
     */
    public function scopePermanentlyFailed(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('status', 'failed')
            ->whereNotNull('permanently_failed_at');
    }

    /**
     * Mark job as permanently failed (dead letter queue)
     */
    public function markAsPermanentlyFailed(string $reason = 'max_retries_exceeded'): bool
    {
        return $this->update([
            'permanently_failed_at' => now(),
            'failed_reason' => $reason,
        ]);
    }

    /**
     * Check if job is in dead letter queue
     */
    public function isPermanentlyFailed(): bool
    {
        return $this->status === 'failed' && $this->permanently_failed_at !== null;
    }
}
