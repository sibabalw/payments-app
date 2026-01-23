<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ErrorLog extends Model
{
    /** @use HasFactory<\Database\Factories\ErrorLogFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'level',
        'message',
        'exception',
        'trace',
        'file',
        'line',
        'url',
        'method',
        'ip_address',
        'user_agent',
        'context',
        'is_admin_error',
        'notified',
        'notified_at',
    ];

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'is_admin_error' => 'boolean',
            'notified' => 'boolean',
            'notified_at' => 'datetime',
            'line' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
