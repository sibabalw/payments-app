<?php

namespace App\Services\Idempotency;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DatabaseIdempotencyService implements IdempotencyServiceInterface
{
    protected string $connection;

    protected string $table;

    public function __construct(?string $connection = null, ?string $table = null)
    {
        $this->connection = $connection ?? config('idempotency.drivers.database.connection', config('database.default'));
        $this->table = $table ?? config('idempotency.drivers.database.table', 'idempotency_keys');
    }

    /**
     * Execute a callback with idempotency protection.
     * Supports content-based deduplication via request_hash.
     */
    public function execute(string $idempotencyKey, callable $callback, int $ttlSeconds = 86400, ?string $requestHash = null)
    {
        return DB::connection($this->connection)->transaction(function () use ($idempotencyKey, $callback, $ttlSeconds, $requestHash) {
            // Check if key exists and is not expired
            $existing = DB::connection($this->connection)
                ->table($this->table)
                ->where('idempotency_key', $idempotencyKey)
                ->where(function ($query) {
                    $query->whereNull('expires_at')
                        ->orWhere('expires_at', '>', now());
                })
                ->lockForUpdate()
                ->first();

            if ($existing) {
                Log::info('Idempotency key found, returning cached response', [
                    'idempotency_key' => $idempotencyKey,
                ]);

                return json_decode($existing->response, true);
            }

            // If request hash provided, check for duplicate requests within deduplication window
            if ($requestHash) {
                $deduplicationWindow = (int) config('idempotency.deduplication_window_seconds', 3600); // Default 1 hour
                $duplicate = DB::connection($this->connection)
                    ->table($this->table)
                    ->where('request_hash', $requestHash)
                    ->where('created_at', '>=', now()->subSeconds($deduplicationWindow))
                    ->where(function ($query) {
                        $query->whereNull('expires_at')
                            ->orWhere('expires_at', '>', now());
                    })
                    ->first();

                if ($duplicate) {
                    Log::info('Duplicate request detected via content hash, returning cached response', [
                        'idempotency_key' => $idempotencyKey,
                        'request_hash' => $requestHash,
                        'original_key' => $duplicate->idempotency_key,
                    ]);

                    return json_decode($duplicate->response, true);
                }
            }

            // Execute callback and store result
            try {
                $result = $callback();

                $expiresAt = Carbon::now()->addSeconds($ttlSeconds);

                DB::connection($this->connection)->table($this->table)->insert([
                    'idempotency_key' => $idempotencyKey,
                    'request_hash' => $requestHash,
                    'response' => json_encode($result),
                    'expires_at' => $expiresAt,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                Log::info('Idempotency key created', [
                    'idempotency_key' => $idempotencyKey,
                    'request_hash' => $requestHash,
                    'expires_at' => $expiresAt,
                ]);

                return $result;
            } catch (\Exception $e) {
                Log::error('Error executing idempotent operation', [
                    'idempotency_key' => $idempotencyKey,
                    'error' => $e->getMessage(),
                ]);

                throw $e;
            }
        });
    }

    /**
     * Check if an idempotency key exists and return cached response if available.
     */
    public function check(string $idempotencyKey)
    {
        $existing = DB::connection($this->connection)
            ->table($this->table)
            ->where('idempotency_key', $idempotencyKey)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->first();

        if ($existing) {
            return json_decode($existing->response, true);
        }

        return null;
    }

    /**
     * Record an idempotency key with a response.
     */
    public function record(string $idempotencyKey, $response, int $ttlSeconds = 86400, ?string $requestHash = null): void
    {
        DB::connection($this->connection)->transaction(function () use ($idempotencyKey, $response, $ttlSeconds, $requestHash) {
            $expiresAt = Carbon::now()->addSeconds($ttlSeconds);

            DB::connection($this->connection)->table($this->table)->insert([
                'idempotency_key' => $idempotencyKey,
                'request_hash' => $requestHash,
                'response' => json_encode($response),
                'expires_at' => $expiresAt,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    /**
     * Generate a content-based hash for request deduplication
     */
    public function generateRequestHash(array $data): string
    {
        // Sort data to ensure consistent hashing
        ksort($data);
        $serialized = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return hash('sha256', $serialized);
    }

    /**
     * Rotate idempotency key for long-running operations
     * Creates a new key while preserving the old one for a grace period
     */
    public function rotateKey(string $oldKey, int $gracePeriodSeconds = 300): string
    {
        $newKey = $oldKey.'_'.Str::random(8).'_'.time();

        // Extend expiration of old key for grace period
        DB::connection($this->connection)
            ->table($this->table)
            ->where('idempotency_key', $oldKey)
            ->update([
                'expires_at' => Carbon::now()->addSeconds($gracePeriodSeconds),
                'updated_at' => now(),
            ]);

        Log::info('Idempotency key rotated', [
            'old_key' => $oldKey,
            'new_key' => $newKey,
            'grace_period' => $gracePeriodSeconds,
        ]);

        return $newKey;
    }
}
