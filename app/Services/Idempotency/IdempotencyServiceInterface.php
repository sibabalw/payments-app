<?php

namespace App\Services\Idempotency;

interface IdempotencyServiceInterface
{
    /**
     * Execute a callback with idempotency protection.
     *
     * @param string $idempotencyKey Unique key for this operation
     * @param callable $callback Function to execute if not cached
     * @param int $ttlSeconds Time to live in seconds
     * @return mixed Result from callback or cached response
     */
    public function execute(string $idempotencyKey, callable $callback, int $ttlSeconds = 86400);

    /**
     * Check if an idempotency key exists and return cached response if available.
     *
     * @param string $idempotencyKey
     * @return mixed|null Cached response or null if not found
     */
    public function check(string $idempotencyKey);

    /**
     * Record an idempotency key with a response.
     *
     * @param string $idempotencyKey
     * @param mixed $response
     * @param int $ttlSeconds
     * @return void
     */
    public function record(string $idempotencyKey, $response, int $ttlSeconds = 86400): void;
}
