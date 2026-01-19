<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'business_id',
        'leave_type',
        'start_date',
        'end_date',
        'hours',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'hours' => 'decimal:2',
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
     * Check if leave applies to a specific date
     */
    public function isActiveOnDate(Carbon $date): bool
    {
        $start = Carbon::parse($this->start_date)->startOfDay();
        $end = Carbon::parse($this->end_date)->endOfDay();
        $checkDate = $date->copy()->startOfDay();

        return $checkDate->between($start, $end);
    }

    /**
     * Get total hours for paid leave
     */
    public function getTotalHours(): float
    {
        if ($this->leave_type === 'paid') {
            return (float) $this->hours;
        }

        return 0;
    }

    /**
     * Get number of days in leave period
     */
    public function getDaysCount(): int
    {
        $start = Carbon::parse($this->start_date);
        $end = Carbon::parse($this->end_date);

        return $start->diffInDays($end) + 1;
    }
}
