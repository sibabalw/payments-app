<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportGeneration extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'business_id',
        'report_type',
        'format',
        'delivery_method',
        'status',
        'file_path',
        'filename',
        'error_message',
        'parameters',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'parameters' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * Mark report as processing (atomic update)
     */
    public function markAsProcessing(): void
    {
        \Illuminate\Support\Facades\DB::transaction(function () {
            $this->lockForUpdate();
            $this->update([
                'status' => 'processing',
                'started_at' => now(),
            ]);
        });
    }

    /**
     * Mark report as completed (atomic update)
     */
    public function markAsCompleted(string $filePath, string $filename): void
    {
        \Illuminate\Support\Facades\DB::transaction(function () use ($filePath, $filename) {
            $this->lockForUpdate();
            $this->update([
                'status' => 'completed',
                'file_path' => $filePath,
                'filename' => $filename,
                'completed_at' => now(),
            ]);
        });
    }

    /**
     * Mark report as failed (atomic update)
     */
    public function markAsFailed(string $errorMessage): void
    {
        \Illuminate\Support\Facades\DB::transaction(function () use ($errorMessage) {
            $this->lockForUpdate();
            $this->update([
                'status' => 'failed',
                'error_message' => $errorMessage,
                'completed_at' => now(),
            ]);
        });
    }

    /**
     * Get fresh instance with lock for consistent reads
     */
    public function getFreshWithLock(): self
    {
        return static::where('id', $this->id)
            ->lockForUpdate()
            ->firstOrFail();
    }
}
