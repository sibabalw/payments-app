<?php

namespace App\Services;

use App\Services\Idempotency\DatabaseIdempotencyService;
use App\Services\Idempotency\IdempotencyServiceInterface;
use App\Services\Idempotency\RedisIdempotencyService;
use Illuminate\Support\Facades\DB;

class IdempotencyService
{
    protected IdempotencyServiceInterface $driver;

    public function __construct()
    {
        $driver = config('idempotency.driver', 'database');

        // Check if Redis is enabled for idempotency
        if ($driver === 'redis' && config('features.redis.idempotency', false)) {
            $this->driver = new RedisIdempotencyService(
                config('idempotency.drivers.redis.connection'),
                config('idempotency.drivers.redis.prefix')
            );
        } else {
            // Default to database
            $this->driver = new DatabaseIdempotencyService(
                config('idempotency.drivers.database.connection'),
                config('idempotency.drivers.database.table')
            );
        }
    }

    /**
     * Execute a callback with idempotency protection.
     * Returns cached response if key exists, otherwise executes callback and caches result.
     * Supports content-based deduplication via request data.
     *
     * @param  string  $idempotencyKey  Unique key for this operation
     * @param  callable  $callback  Function to execute if not cached
     * @param  int  $ttlSeconds  Time to live in seconds (default: 24 hours)
     * @param  array|null  $requestData  Optional request data for content-based deduplication
     * @return mixed Result from callback or cached response
     */
    public function execute(string $idempotencyKey, callable $callback, int $ttlSeconds = 86400, ?array $requestData = null)
    {
        $requestHash = null;
        if ($requestData && $this->driver instanceof DatabaseIdempotencyService) {
            $requestHash = $this->driver->generateRequestHash($requestData);
        }

        return $this->driver->execute($idempotencyKey, $callback, $ttlSeconds, $requestHash);
    }

    /**
     * Check if an idempotency key exists and return cached response if available.
     *
     * @return mixed|null Cached response or null if not found
     */
    public function check(string $idempotencyKey)
    {
        return $this->driver->check($idempotencyKey);
    }

    /**
     * Record an idempotency key with a response.
     *
     * @param  mixed  $response
     * @param  array|null  $requestData  Optional request data for content-based deduplication
     */
    public function record(string $idempotencyKey, $response, int $ttlSeconds = 86400, ?array $requestData = null): void
    {
        $requestHash = null;
        if ($requestData && $this->driver instanceof DatabaseIdempotencyService) {
            $requestHash = $this->driver->generateRequestHash($requestData);
        }

        $this->driver->record($idempotencyKey, $response, $ttlSeconds, $requestHash);
    }

    /**
     * Rotate idempotency key for long-running operations
     */
    public function rotateKey(string $oldKey, int $gracePeriodSeconds = 300): string
    {
        if ($this->driver instanceof DatabaseIdempotencyService) {
            return $this->driver->rotateKey($oldKey, $gracePeriodSeconds);
        }

        // Fallback: generate new key
        return $oldKey.'_'.time();
    }

    /**
     * Clean up expired idempotency keys (database only).
     *
     * @return int Number of keys deleted
     */
    public function cleanup(): int
    {
        // Only applicable for database driver
        if ($this->driver instanceof DatabaseIdempotencyService) {
            return DB::table('idempotency_keys')
                ->where('expires_at', '<=', now())
                ->delete();
        }

        return 0;
    }
}
