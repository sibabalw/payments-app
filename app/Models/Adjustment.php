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
        'period_start',
        'period_end',
        'is_active',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'is_active' => 'boolean',
            'period_start' => 'date',
            'period_end' => 'date',
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
     * Recurring adjustments (period_start/end = null) are always valid
     * Once-off adjustments (period_start/end set) must overlap with the given period
     */
    public function isValidForPeriod(Carbon $periodStart, Carbon $periodEnd): bool
    {
        // Recurring adjustments (null period) are always valid
        if ($this->period_start === null && $this->period_end === null) {
            return true;
        }

        // Once-off adjustments must overlap with the period
        if ($this->period_start === null || $this->period_end === null) {
            return false;
        }

        $adjustmentStart = Carbon::parse($this->period_start);
        $adjustmentEnd = Carbon::parse($this->period_end);

        return $adjustmentStart->lte($periodEnd) && $adjustmentEnd->gte($periodStart);
    }

    /**
     * Check if this is a recurring adjustment
     * Recurring = period_start and period_end are both null
     */
    public function isRecurring(): bool
    {
        return $this->period_start === null && $this->period_end === null;
    }

    /**
     * Scope a query to only include recurring adjustments
     * Recurring = period_start and period_end are both null
     */
    public function scopeRecurring(Builder $query): Builder
    {
        return $query->whereNull('period_start')
            ->whereNull('period_end');
    }

    /**
     * Scope a query to only include once-off adjustments
     * Once-off = period_start and period_end are both set
     */
    public function scopeOnceOff(Builder $query): Builder
    {
        return $query->whereNotNull('period_start')
            ->whereNotNull('period_end');
    }

    /**
     * Scope a query to only include adjustments valid for a specific period
     * Includes recurring (null period) and once-off that overlap
     */
    public function scopeForPeriod(Builder $query, Carbon $periodStart, Carbon $periodEnd): Builder
    {
        return $query->where(function ($q) use ($periodStart, $periodEnd) {
            // Recurring adjustments (null period)
            $q->whereNull('period_start')
                ->whereNull('period_end')
                // Or once-off adjustments that overlap with the period
                ->orWhere(function ($subQ) use ($periodStart, $periodEnd) {
                    $subQ->whereNotNull('period_start')
                        ->whereNotNull('period_end')
                        ->where('period_start', '<=', $periodEnd)
                        ->where('period_end', '>=', $periodStart);
                });
        });
    }
}
