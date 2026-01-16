<?php

namespace App\Services\Idempotency;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

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
     */
    public function execute(string $idempotencyKey, callable $callback, int $ttlSeconds = 86400)
    {
        return DB::connection($this->connection)->transaction(function () use ($idempotencyKey, $callback, $ttlSeconds) {
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

            // Execute callback and store result
            try {
                $result = $callback();

                $expiresAt = Carbon::now()->addSeconds($ttlSeconds);

                DB::connection($this->connection)->table($this->table)->insert([
                    'idempotency_key' => $idempotencyKey,
                    'request_hash' => null,
                    'response' => json_encode($result),
                    'expires_at' => $expiresAt,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                Log::info('Idempotency key created', [
                    'idempotency_key' => $idempotencyKey,
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
    public function record(string $idempotencyKey, $response, int $ttlSeconds = 86400): void
    {
        DB::connection($this->connection)->transaction(function () use ($idempotencyKey, $response, $ttlSeconds) {
            $expiresAt = Carbon::now()->addSeconds($ttlSeconds);

            DB::connection($this->connection)->table($this->table)->insert([
                'idempotency_key' => $idempotencyKey,
                'request_hash' => null,
                'response' => json_encode($response),
                'expires_at' => $expiresAt,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }
}
