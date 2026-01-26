<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Employee extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'name',
        'email',
        'id_number',
        'tax_number',
        'employment_type',
        'hours_worked_per_month',
        'department',
        'start_date',
        'gross_salary',
        'hourly_rate',
        'overtime_rate_multiplier',
        'weekend_rate_multiplier',
        'holiday_rate_multiplier',
        'bank_account_details',
        'tax_status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'gross_salary' => 'decimal:2',
            'hours_worked_per_month' => 'decimal:2',
            'hourly_rate' => 'decimal:2',
            'overtime_rate_multiplier' => 'decimal:2',
            'weekend_rate_multiplier' => 'decimal:2',
            'holiday_rate_multiplier' => 'decimal:2',
            'bank_account_details' => 'array',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function payrollSchedules(): BelongsToMany
    {
        return $this->belongsToMany(PayrollSchedule::class, 'payroll_schedule_employee')
            ->withTimestamps();
    }

    public function payrollJobs(): HasMany
    {
        return $this->hasMany(PayrollJob::class);
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(Adjustment::class);
    }

    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(EmployeeSchedule::class);
    }

    public function leaveEntries(): HasMany
    {
        return $this->hasMany(LeaveEntry::class);
    }

    /**
     * Check if employee is exempt from UIF
     * According to SARS: Employees working fewer than 24 hours per month are exempt
     * Optimized to use eager loaded time entries when available
     */
    public function isUIFExempt(): bool
    {
        // Use eager loaded time entries if available
        if ($this->relationLoaded('timeEntries')) {
            $timeEntries = $this->timeEntries;
        } else {
            // Otherwise query them
            $timeEntries = $this->timeEntries()
                ->whereNotNull('sign_out_time')
                ->get();
        }

        if ($timeEntries->isNotEmpty()) {
            // Calculate total hours from time entries for current month
            $currentMonth = now()->startOfMonth();
            $currentMonthEnd = now()->endOfMonth();

            $monthlyHours = $timeEntries
                ->filter(function ($entry) use ($currentMonth, $currentMonthEnd) {
                    $entryDate = \Carbon\Carbon::parse($entry->date);

                    return $entryDate->between($currentMonth, $currentMonthEnd);
                })
                ->sum(function ($entry) {
                    return (float) $entry->regular_hours
                        + (float) $entry->overtime_hours
                        + (float) $entry->weekend_hours
                        + (float) $entry->holiday_hours;
                });

            return $monthlyHours < 24;
        }

        // Fallback to hours_worked_per_month field if no time entries
        if ($this->hours_worked_per_month === null) {
            return false;
        }

        return $this->hours_worked_per_month < 24;
    }

    public function getFormattedGrossSalaryAttribute(): string
    {
        return 'ZAR '.number_format($this->gross_salary, 2);
    }
}
