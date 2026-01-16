<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExecutedPayroll extends Model
{
    use HasFactory;

    protected $table = 'executed_payroll';

    protected $fillable = [
        'payroll_schedule_id',
        'employee_id',
        'gross_salary',
        'paye_amount',
        'uif_amount',
        'sdl_amount',
        'net_salary',
        'currency',
        'status',
        'error_message',
        'processed_at',
        'transaction_id',
        'fee',
        'escrow_deposit_id',
        'fee_released_manually_at',
        'funds_returned_manually_at',
        'released_by',
        'pay_period_start',
        'pay_period_end',
    ];

    protected function casts(): array
    {
        return [
            'gross_salary' => 'decimal:2',
            'paye_amount' => 'decimal:2',
            'uif_amount' => 'decimal:2',
            'sdl_amount' => 'decimal:2',
            'net_salary' => 'decimal:2',
            'fee' => 'decimal:2',
            'processed_at' => 'datetime',
            'fee_released_manually_at' => 'datetime',
            'funds_returned_manually_at' => 'datetime',
            'pay_period_start' => 'date',
            'pay_period_end' => 'date',
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

    public function getTaxBreakdownAttribute(): array
    {
        return [
            'gross' => $this->gross_salary,
            'paye' => $this->paye_amount,
            'uif' => $this->uif_amount,
            'sdl' => $this->sdl_amount,
            'net' => $this->net_salary,
            'total_deductions' => $this->paye_amount + $this->uif_amount + $this->sdl_amount,
        ];
    }
}
