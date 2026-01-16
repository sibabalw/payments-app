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
        'department',
        'start_date',
        'gross_salary',
        'bank_account_details',
        'tax_status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'gross_salary' => 'decimal:2',
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

    public function getFormattedGrossSalaryAttribute(): string
    {
        return 'ZAR ' . number_format($this->gross_salary, 2);
    }
}
