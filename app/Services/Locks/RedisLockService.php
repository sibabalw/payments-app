<?php

namespace App\Services\Locks;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class RedisLockService implements LockServiceInterface
{
    protected string $connection;

    protected array $acquiredLocks = [];

    public function __construct(?string $connection = null)
    {
        $this->connection = $connection ?? 'default';
    }

    /**
     * Acquire a lock using Redis.
     *
     * @param  string  $key  Lock key
     * @param  int  $timeout  Maximum seconds to wait for lock
     * @param  int  $expiration  Seconds until lock expires
     * @return bool True if lock acquired, false otherwise
     */
    public function acquire(string $key, int $timeout = 10, int $expiration = 300): bool
    {
        try {
            $redis = Redis::connection($this->connection);
            $lockKey = "lock:{$key}";
            $owner = uniqid('', true);
            $endTime = time() + $timeout;

            while (time() < $endTime) {
                // Try to acquire lock with SET NX EX
                $acquired = $redis->set($lockKey, $owner, 'EX', $expiration, 'NX');

                if ($acquired) {
                    $this->acquiredLocks[$key] = $owner;

                    return true;
                }

                // Wait a bit before retrying
                usleep(100000); // 100ms
            }

            return false;
        } catch (\Exception $e) {
            Log::error('Error acquiring Redis lock', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Release a lock.
     *
     * @param  string  $key  Lock key
     * @return bool True if lock released, false otherwise
     */
    public function release(string $key): bool
    {
        try {
            if (! isset($this->acquiredLocks[$key])) {
                return false;
            }

            $redis = Redis::connection($this->connection);
            $lockKey = "lock:{$key}";
            $owner = $this->acquiredLocks[$key];

            // Use Lua script to ensure we only release our own lock
            $script = "
                if redis.call('get', KEYS[1]) == ARGV[1] then
                    return redis.call('del', KEYS[1])
                else
                    return 0
                end
            ";

            $released = $redis->eval($script, 1, $lockKey, $owner);

            if ($released) {
                unset($this->acquiredLocks[$key]);

                return true;
            }

            return false;
        } catch (\Exception $e) {
            Log::error('Error releasing Redis lock', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Execute a callback while holding a lock.
     *
     * @param  string  $key  Lock key
     * @param  callable  $callback  Callback to execute
     * @param  int  $timeout  Maximum seconds to wait for lock
     * @param  int  $expiration  Seconds until lock expires
     * @return mixed Result from callback
     *
     * @throws \Exception If lock cannot be acquired
     */
    public function block(string $key, callable $callback, int $timeout = 10, int $expiration = 300)
    {
        $acquired = $this->acquire($key, $timeout, $expiration);

        if (! $acquired) {
            throw new \Exception("Could not acquire lock: {$key}");
        }

        try {
            return $callback();
        } finally {
            $this->release($key);
        }
    }

    /**
     * Extend lock expiration (heartbeat) to prevent lock from expiring during long operations.
     */
    public function heartbeat(string $key, int $expiration = 300): bool
    {
        try {
            if (! isset($this->acquiredLocks[$key])) {
                return false;
            }

            $redis = Redis::connection($this->connection);
            $lockKey = "lock:{$key}";
            $owner = $this->acquiredLocks[$key];

            // Use Lua script to extend expiration only if we own the lock
            $script = "
                if redis.call('get', KEYS[1]) == ARGV[1] then
                    return redis.call('expire', KEYS[1], ARGV[2])
                else
                    return 0
                end
            ";

            $extended = $redis->eval($script, 1, $lockKey, $owner, $expiration);

            return (bool) $extended;
        } catch (\Exception $e) {
            Log::error('Error sending Redis lock heartbeat', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
