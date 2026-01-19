<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'day_of_week',
        'scheduled_hours',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_hours' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Get scheduled hours for a specific date
     */
    public static function getScheduledHoursForDate(Employee $employee, Carbon $date): float
    {
        $dayOfWeek = (int) $date->format('w'); // 0=Sunday, 6=Saturday

        $schedule = self::where('employee_id', $employee->id)
            ->where('day_of_week', $dayOfWeek)
            ->where('is_active', true)
            ->first();

        return $schedule ? (float) $schedule->scheduled_hours : 0;
    }

    /**
     * Check if employee is scheduled to work on a specific date
     */
    public static function isScheduledDay(Employee $employee, Carbon $date): bool
    {
        return self::getScheduledHoursForDate($employee, $date) > 0;
    }

    /**
     * Get day name from day of week number
     */
    public function getDayNameAttribute(): string
    {
        $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

        return $days[$this->day_of_week] ?? 'Unknown';
    }
}
