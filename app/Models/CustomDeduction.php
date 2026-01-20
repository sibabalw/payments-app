<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomDeduction extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'employee_id',
        'name',
        'type',
        'amount',
        'is_active',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'is_active' => 'boolean',
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
     * Check if this is a company-wide deduction
     */
    public function isCompanyWide(): bool
    {
        return $this->employee_id === null;
    }

    /**
     * Calculate the deduction amount for a given gross salary
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
}
