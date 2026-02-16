<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimeEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'business_id',
        'date',
        'sign_in_time',
        'sign_out_time',
        'regular_hours',
        'overtime_hours',
        'weekend_hours',
        'holiday_hours',
        'bonus_amount',
        'notes',
        'entry_type',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'sign_in_time' => 'datetime',
            'sign_out_time' => 'datetime',
            'regular_hours' => 'decimal:2',
            'overtime_hours' => 'decimal:2',
            'weekend_hours' => 'decimal:2',
            'holiday_hours' => 'decimal:2',
            'bonus_amount' => 'decimal:2',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Calculate total hours (regular + overtime + weekend + holiday)
     */
    public function getTotalHours(): float
    {
        return (float) $this->regular_hours
            + (float) $this->overtime_hours
            + (float) $this->weekend_hours
            + (float) $this->holiday_hours;
    }

    /**
     * Calculate total earnings for this entry
     */
    public function getTotalEarnings(): float
    {
        if (! $this->employee || ! $this->employee->hourly_rate) {
            return (float) $this->bonus_amount;
        }

        $hourlyRate = (float) $this->employee->hourly_rate;
        $regularEarnings = (float) $this->regular_hours * $hourlyRate;

        $overtimeRate = $hourlyRate * (float) ($this->employee->overtime_rate_multiplier ?? 1.5);
        $overtimeEarnings = (float) $this->overtime_hours * $overtimeRate;

        $weekendRate = $hourlyRate * (float) ($this->employee->weekend_rate_multiplier ?? 1.5);
        $weekendEarnings = (float) $this->weekend_hours * $weekendRate;

        $holidayRate = $hourlyRate * (float) ($this->employee->holiday_rate_multiplier ?? 2.0);
        $holidayEarnings = (float) $this->holiday_hours * $holidayRate;

        $bonus = (float) $this->bonus_amount;

        return round($regularEarnings + $overtimeEarnings + $weekendEarnings + $holidayEarnings + $bonus, 2);
    }

    /**
     * Check if employee is currently signed in (has sign_in_time but no sign_out_time)
     */
    public function isSignedIn(): bool
    {
        return $this->sign_in_time !== null && $this->sign_out_time === null;
    }

    /**
     * Get hours worked from sign in/out times
     */
    public function calculateHoursFromTimes(): float
    {
        if (! $this->sign_in_time || ! $this->sign_out_time) {
            return 0;
        }

        $signIn = Carbon::parse($this->sign_in_time);
        $signOut = Carbon::parse($this->sign_out_time);

        return round($signOut->diffInHours($signIn, true), 2);
    }
}
