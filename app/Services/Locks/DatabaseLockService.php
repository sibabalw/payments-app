<?php

namespace App\Services\Locks;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DatabaseLockService implements LockServiceInterface
{
    protected string $connection;

    public function __construct(?string $connection = null)
    {
        $this->connection = $connection ?? config('database.default');
    }

    /**
     * Acquire a lock using database advisory locks or row locks.
     *
     * @param  string  $key  Lock key
     * @param  int  $timeout  Maximum seconds to wait for lock
     * @param  int  $expiration  Seconds until lock expires
     * @return bool True if lock acquired, false otherwise
     */
    public function acquire(string $key, int $timeout = 10, int $expiration = 300): bool
    {
        $driver = DB::connection($this->connection)->getDriverName();

        if ($driver === 'pgsql') {
            // PostgreSQL advisory locks
            $lockId = $this->getLockId($key);
            $result = DB::connection($this->connection)
                ->selectOne('SELECT pg_try_advisory_lock(?) as acquired', [$lockId]);

            return (bool) $result->acquired;
        } elseif ($driver === 'mysql' || $driver === 'mariadb') {
            // MySQL GET_LOCK
            $result = DB::connection($this->connection)
                ->selectOne('SELECT GET_LOCK(?, ?) as acquired', [$key, $timeout]);

            return (bool) $result->acquired;
        } else {
            // SQLite or other: use cache_locks table
            return $this->acquireCacheLock($key, $expiration);
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
        $driver = DB::connection($this->connection)->getDriverName();

        if ($driver === 'pgsql') {
            $lockId = $this->getLockId($key);
            $result = DB::connection($this->connection)
                ->selectOne('SELECT pg_advisory_unlock(?) as released', [$lockId]);

            return (bool) $result->released;
        } elseif ($driver === 'mysql' || $driver === 'mariadb') {
            $result = DB::connection($this->connection)
                ->selectOne('SELECT RELEASE_LOCK(?) as released', [$key]);

            return (bool) $result->released;
        } else {
            return $this->releaseCacheLock($key);
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
     * Get a numeric lock ID from a string key (for PostgreSQL).
     */
    protected function getLockId(string $key): int
    {
        return crc32($key);
    }

    /**
     * Acquire a lock using the cache_locks table.
     */
    protected function acquireCacheLock(string $key, int $expiration): bool
    {
        try {
            $owner = uniqid('', true);
            $expiresAt = time() + $expiration;

            // Try to insert lock
            $inserted = DB::table('cache_locks')->insert([
                'key' => $key,
                'owner' => $owner,
                'expiration' => $expiresAt,
            ]);

            if ($inserted) {
                return true;
            }

            // Check if existing lock is expired
            $existing = DB::table('cache_locks')
                ->where('key', $key)
                ->first();

            if ($existing && $existing->expiration < time()) {
                // Lock expired, try to acquire it
                $deleted = DB::table('cache_locks')
                    ->where('key', $key)
                    ->where('expiration', '<', time())
                    ->delete();

                if ($deleted) {
                    return DB::table('cache_locks')->insert([
                        'key' => $key,
                        'owner' => $owner,
                        'expiration' => $expiresAt,
                    ]);
                }
            }

            return false;
        } catch (\Exception $e) {
            Log::error('Error acquiring cache lock', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Release a lock using the cache_locks table.
     */
    protected function releaseCacheLock(string $key): bool
    {
        try {
            return DB::table('cache_locks')
                ->where('key', $key)
                ->delete() > 0;
        } catch (\Exception $e) {
            Log::error('Error releasing cache lock', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Extend lock expiration (heartbeat) for cache locks.
     * Note: PostgreSQL and MySQL advisory locks don't expire, so this only applies to cache locks.
     */
    public function heartbeat(string $key, int $expiration = 300): bool
    {
        $driver = DB::connection($this->connection)->getDriverName();

        // PostgreSQL and MySQL advisory locks don't expire, so heartbeat is a no-op
        if ($driver === 'pgsql' || $driver === 'mysql' || $driver === 'mariadb') {
            return true; // Lock doesn't expire, so heartbeat always succeeds
        }

        // For cache locks, extend expiration
        try {
            $expiresAt = time() + $expiration;
            $updated = DB::table('cache_locks')
                ->where('key', $key)
                ->where('expiration', '>', time()) // Only extend if not already expired
                ->update(['expiration' => $expiresAt]);

            return $updated > 0;
        } catch (\Exception $e) {
            Log::error('Error sending lock heartbeat', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
