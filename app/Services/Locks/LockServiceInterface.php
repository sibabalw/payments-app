<?php

namespace App\Services\Locks;

interface LockServiceInterface
{
    /**
     * Acquire a lock.
     *
     * @param string $key Lock key
     * @param int $timeout Maximum seconds to wait for lock
     * @param int $expiration Seconds until lock expires
     * @return bool True if lock acquired, false otherwise
     */
    public function acquire(string $key, int $timeout = 10, int $expiration = 300): bool;

    /**
     * Release a lock.
     *
     * @param string $key Lock key
     * @return bool True if lock released, false otherwise
     */
    public function release(string $key): bool;

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
    public function block(string $key, callable $callback, int $timeout = 10, int $expiration = 300);
}
