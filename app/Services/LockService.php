<?php

namespace App\Services;

use App\Services\Locks\DatabaseLockService;
use App\Services\Locks\LockServiceInterface;
use App\Services\Locks\RedisLockService;

class LockService
{
    protected LockServiceInterface $driver;

    public function __construct()
    {
        $driver = config('locks.driver', 'database');

        // Check if Redis is enabled for locks
        if ($driver === 'redis' && config('features.redis.locks', false)) {
            $this->driver = new RedisLockService(config('locks.drivers.redis.connection'));
        } else {
            // Default to database
            $this->driver = new DatabaseLockService(config('locks.drivers.database.connection'));
        }
    }

    /**
     * Acquire a lock.
     *
     * @param string $key Lock key
     * @param int $timeout Maximum seconds to wait for lock
     * @param int $expiration Seconds until lock expires
     * @return bool True if lock acquired, false otherwise
     */
    public function acquire(string $key, int $timeout = 10, int $expiration = 300): bool
    {
        return $this->driver->acquire($key, $timeout, $expiration);
    }

    /**
     * Release a lock.
     *
     * @param string $key Lock key
     * @return bool True if lock released, false otherwise
     */
    public function release(string $key): bool
    {
        return $this->driver->release($key);
    }

    /**
     * Execute a callback while holding a lock.
     *
     * @param string $key Lock key
     * @param callable $callback Callback to execute
     * @param int $timeout Maximum seconds to wait for lock
     * @param int $expiration Seconds until lock expires
     * @return mixed Result from callback
     * @throws \Exception If lock cannot be acquired
     */
    public function block(string $key, callable $callback, int $timeout = 10, int $expiration = 300)
    {
        return $this->driver->block($key, $callback, $timeout, $expiration);
    }
}
