<?php

namespace App\Services\CircuitBreaker;

interface StorageInterface
{
    /**
     * Get circuit breaker state
     */
    public function getState(string $key): ?array;

    /**
     * Set circuit breaker state
     */
    public function setState(string $key, State $state, int $failureCount, ?int $lastFailureTime = null): void;

    /**
     * Increment failure count
     */
    public function incrementFailure(string $key): int;

    /**
     * Reset failure count
     */
    public function resetFailure(string $key): void;

    /**
     * Record successful request
     */
    public function recordSuccess(string $key): void;
}
