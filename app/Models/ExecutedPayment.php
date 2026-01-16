<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExecutedPayment extends Model
{
    use HasFactory;

    protected $table = 'executed_payments';

    protected $fillable = [
        'payment_schedule_id',
        'recipient_id',
        'amount',
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
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'fee' => 'decimal:2',
            'processed_at' => 'datetime',
            'fee_released_manually_at' => 'datetime',
            'funds_returned_manually_at' => 'datetime',
        ];
    }

    public function paymentSchedule(): BelongsTo
    {
        return $this->belongsTo(PaymentSchedule::class);
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(Recipient::class);
    }

    public function escrowDeposit(): BelongsTo
    {
        return $this->belongsTo(EscrowDeposit::class);
    }

    public function releasedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by');
    }
}
