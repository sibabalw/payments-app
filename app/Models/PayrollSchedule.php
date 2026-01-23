<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'name',
        'frequency',
        'schedule_type',
        'status',
        'next_run_at',
        'last_run_at',
    ];

    protected function casts(): array
    {
        return [
            'next_run_at' => 'datetime',
            'last_run_at' => 'datetime',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function employees(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class, 'payroll_schedule_employee')
            ->withTimestamps();
    }

    public function payrollJobs(): HasMany
    {
        return $this->hasMany(PayrollJob::class);
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

    /**
     * Calculate the pay period this schedule will process when it executes.
     *
     * This method determines what pay period (start and end dates) the schedule
     * will use when processing payroll. The calculation is based on:
     * - Monthly recurring schedules: pay for the previous month
     * - One-time schedules: pay for the scheduled month
     * - Other schedules: use current month
     *
     * @param  Carbon|null  $executionDate  If null, uses next_run_at or now()
     * @return array{start: Carbon, end: Carbon}
     */
    public function calculatePayPeriod(?Carbon $executionDate = null): array
    {
        $executionDate = $executionDate ?? $this->next_run_at ?? now();

        // Ensure we're working with a Carbon instance
        if (! $executionDate instanceof Carbon) {
            $executionDate = Carbon::parse($executionDate);
        }

        // Parse cron frequency to determine schedule type
        $cronParts = explode(' ', $this->frequency);

        // For monthly recurring schedules: pay for previous month
        // Cron format: "0 0 25 * *" (runs on 25th) means pay for previous month
        if ($this->isRecurring() && count($cronParts) === 5) {
            // Check if it's a monthly schedule (day of month is specified, not *)
            if ($cronParts[2] !== '*' && $cronParts[3] === '*') {
                $previousMonth = $executionDate->copy()->subMonth();

                return [
                    'start' => $previousMonth->copy()->startOfMonth(),
                    'end' => $previousMonth->copy()->endOfMonth(),
                ];
            }
        }

        // For one-time schedules: pay for the month they're scheduled in
        if ($this->isOneTime()) {
            return [
                'start' => $executionDate->copy()->startOfMonth(),
                'end' => $executionDate->copy()->endOfMonth(),
            ];
        }

        // For weekly/biweekly/daily: use current month (default behavior)
        return [
            'start' => $executionDate->copy()->startOfMonth(),
            'end' => $executionDate->copy()->endOfMonth(),
        ];
    }
}
