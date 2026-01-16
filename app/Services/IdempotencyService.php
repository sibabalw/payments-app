<?php

namespace App\Services;

use App\Services\Idempotency\DatabaseIdempotencyService;
use App\Services\Idempotency\IdempotencyServiceInterface;
use App\Services\Idempotency\RedisIdempotencyService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
     *
     * @param string $idempotencyKey Unique key for this operation
     * @param callable $callback Function to execute if not cached
     * @param int $ttlSeconds Time to live in seconds (default: 24 hours)
     * @return mixed Result from callback or cached response
     */
    public function execute(string $idempotencyKey, callable $callback, int $ttlSeconds = 86400)
    {
        return $this->driver->execute($idempotencyKey, $callback, $ttlSeconds);
    }

    /**
     * Check if an idempotency key exists and return cached response if available.
     *
     * @param string $idempotencyKey
     * @return mixed|null Cached response or null if not found
     */
    public function check(string $idempotencyKey)
    {
        return $this->driver->check($idempotencyKey);
    }

    /**
     * Record an idempotency key with a response.
     *
     * @param string $idempotencyKey
     * @param mixed $response
     * @param int $ttlSeconds
     * @return void
     */
    public function record(string $idempotencyKey, $response, int $ttlSeconds = 86400): void
    {
        $this->driver->record($idempotencyKey, $response, $ttlSeconds);
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
