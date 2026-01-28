<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditPack extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'date_from',
        'date_to',
        'pack_filename',
        'pack_hash',
        'ledger_entry_count',
        'audit_log_count',
        'reversal_count',
        'exported_by',
        'exported_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'date_from' => 'date',
            'date_to' => 'date',
            'exported_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function exportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'exported_by');
    }
}
