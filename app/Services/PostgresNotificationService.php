<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * PostgreSQL LISTEN/NOTIFY Service
 *
 * Provides real-time notifications using PostgreSQL's LISTEN/NOTIFY feature.
 * This is optional and can be disabled via feature flag.
 */
class PostgresNotificationService
{
    protected bool $enabled;

    protected ?\PDO $pdo = null;

    public function __construct()
    {
        $this->enabled = config('features.postgres_notifications', false);
    }

    /**
     * Check if notifications are enabled
     */
    public function isEnabled(): bool
    {
        if (! $this->enabled) {
            return false;
        }

        // Check if we're using PostgreSQL
        $driver = DB::connection()->getDriverName();
        if ($driver !== 'pgsql') {
            return false;
        }

        return true;
    }

    /**
     * Send a notification
     *
     * @param  string  $channel  Channel name
     * @param  string  $payload  Notification payload (JSON string)
     */
    public function notify(string $channel, string $payload): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        try {
            DB::statement('SELECT pg_notify(?, ?)', [$channel, $payload]);
        } catch (\Exception $e) {
            // Don't fail the operation if notification fails
            Log::warning('Failed to send PostgreSQL notification', [
                'channel' => $channel,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send a balance update notification
     *
     * @param  int  $businessId  Business ID
     * @param  float  $newBalance  New balance
     * @param  string  $accountType  Account type (ESCROW, etc.)
     */
    public function notifyBalanceUpdate(int $businessId, float $newBalance, string $accountType = 'ESCROW'): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $payload = json_encode([
            'type' => 'balance_update',
            'business_id' => $businessId,
            'account_type' => $accountType,
            'balance' => $newBalance,
            'timestamp' => now()->toIso8601String(),
        ]);

        $this->notify('balance_updates', $payload);
    }

    /**
     * Send a transaction notification
     *
     * @param  string  $correlationId  Correlation ID
     * @param  int  $businessId  Business ID
     * @param  string  $transactionType  Transaction type
     */
    public function notifyTransaction(string $correlationId, int $businessId, string $transactionType): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $payload = json_encode([
            'type' => 'transaction',
            'correlation_id' => $correlationId,
            'business_id' => $businessId,
            'transaction_type' => $transactionType,
            'timestamp' => now()->toIso8601String(),
        ]);

        $this->notify('transactions', $payload);
    }

    /**
     * Send a job status notification
     *
     * @param  string  $jobType  Job type (payroll, payment)
     * @param  int  $jobId  Job ID
     * @param  string  $status  New status
     */
    public function notifyJobStatus(string $jobType, int $jobId, string $status): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $payload = json_encode([
            'type' => 'job_status',
            'job_type' => $jobType,
            'job_id' => $jobId,
            'status' => $status,
            'timestamp' => now()->toIso8601String(),
        ]);

        $this->notify('job_status', $payload);
    }
}
