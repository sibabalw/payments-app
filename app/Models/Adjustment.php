<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Adjustment extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'employee_id',
        'payroll_schedule_id',
        'name',
        'type',
        'amount',
        'adjustment_type',
        'is_recurring',
        'payroll_period_start',
        'payroll_period_end',
        'is_active',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'is_recurring' => 'boolean',
            'is_active' => 'boolean',
            'payroll_period_start' => 'date',
            'payroll_period_end' => 'date',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function payrollSchedule(): BelongsTo
    {
        return $this->belongsTo(PayrollSchedule::class);
    }

    /**
     * Check if this is a company-wide adjustment
     */
    public function isCompanyWide(): bool
    {
        return $this->employee_id === null;
    }

    /**
     * Calculate the adjustment amount for a given gross salary
     */
    public function calculateAmount(float $grossSalary): float
    {
        if (! $this->is_active) {
            return 0;
        }

        if ($this->type === 'percentage') {
            return round($grossSalary * ($this->amount / 100), 2);
        }

        return round($this->amount, 2);
    }

    /**
     * Check if this adjustment is valid for the given payroll period
     */
    public function isValidForPeriod(Carbon $periodStart, Carbon $periodEnd): bool
    {
        // Recurring adjustments are always valid (they apply to all periods)
        if ($this->is_recurring) {
            return true;
        }

        // Once-off adjustments must match the period exactly
        if ($this->payroll_period_start === null || $this->payroll_period_end === null) {
            return false;
        }

        // Check if periods overlap
        $adjustmentStart = Carbon::parse($this->payroll_period_start);
        $adjustmentEnd = Carbon::parse($this->payroll_period_end);

        return $adjustmentStart->lte($periodEnd) && $adjustmentEnd->gte($periodStart);
    }

    /**
     * Scope a query to only include recurring adjustments
     */
    public function scopeRecurring(Builder $query): Builder
    {
        return $query->where('is_recurring', true);
    }

    /**
     * Scope a query to only include once-off adjustments
     */
    public function scopeOnceOff(Builder $query): Builder
    {
        return $query->where('is_recurring', false);
    }

    /**
     * Scope a query to only include adjustments valid for a specific period
     */
    public function scopeForPeriod(Builder $query, Carbon $periodStart, Carbon $periodEnd): Builder
    {
        return $query->where(function ($q) use ($periodStart, $periodEnd) {
            // Recurring adjustments
            $q->where('is_recurring', true)
                // Or once-off adjustments that overlap with the period
                ->orWhere(function ($subQ) use ($periodStart, $periodEnd) {
                    $subQ->where('is_recurring', false)
                        ->where('payroll_period_start', '<=', $periodEnd)
                        ->where('payroll_period_end', '>=', $periodStart);
                });
        });
    }
}
