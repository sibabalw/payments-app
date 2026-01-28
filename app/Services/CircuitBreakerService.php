<?php

namespace App\Services;

use App\Services\CircuitBreaker\DatabaseStorage;
use App\Services\CircuitBreaker\State;
use App\Services\CircuitBreaker\StorageInterface;
use Illuminate\Support\Facades\Log;

class CircuitBreakerService
{
    protected StorageInterface $storage;

    protected int $failureThreshold;

    protected int $timeout;

    protected int $halfOpenSuccessThreshold;

    public function __construct(?StorageInterface $storage = null)
    {
        $this->storage = $storage ?? new DatabaseStorage;
        $this->failureThreshold = (int) config('circuit_breaker.failure_threshold', 5);
        $this->timeout = (int) config('circuit_breaker.timeout', 60); // seconds
        $this->halfOpenSuccessThreshold = (int) config('circuit_breaker.half_open_success_threshold', 3);
    }

    /**
     * Circuit breaker failure domains
     */
    public const DOMAIN_GATEWAY = 'gateway';

    public const DOMAIN_BUSINESS = 'business';

    public const DOMAIN_PAYMENT_METHOD = 'payment_method';

    public const DOMAIN_EMAIL_SERVICE = 'email_service';

    /**
     * Build circuit breaker key with domain scoping
     *
     * Format: {domain}:{identifier}
     * Examples:
     * - gateway:stripe
     * - business:123
     * - payment_method:bank_transfer
     *
     * @param  string  $domain  Failure domain (gateway, business, payment_method, etc.)
     * @param  string  $identifier  Domain-specific identifier
     * @return string Scoped circuit breaker key
     */
    public function buildKey(string $domain, string $identifier): string
    {
        return "{$domain}:{$identifier}";
    }

    /**
     * Execute a callback with circuit breaker protection
     *
     * @param  string  $key  Circuit breaker key (e.g., 'gateway:stripe', 'business:123')
     *                       Use buildKey() to create properly scoped keys
     * @param  callable  $callback  Callback to execute
     * @param  callable|null  $fallback  Fallback callback if circuit is open
     * @return mixed Result from callback or fallback
     *
     * @throws \Exception If circuit is open and no fallback provided
     */
    public function execute(string $key, callable $callback, ?callable $fallback = null): mixed
    {
        $state = $this->getState($key);

        // Check if circuit is open
        if ($state['state'] === State::OPEN) {
            // Check if timeout has passed - transition to half-open
            if ($this->shouldAttemptHalfOpen($key, $state)) {
                $this->transitionToHalfOpen($key);
                $state = $this->getState($key);
            } else {
                // Circuit is still open - use fallback or throw exception
                Log::warning('Circuit breaker is open', [
                    'key' => $key,
                    'failure_count' => $state['failure_count'],
                ]);

                if ($fallback) {
                    return $fallback();
                }

                throw new \RuntimeException("Circuit breaker is open for key: {$key}");
            }
        }

        try {
            // Execute callback
            $result = $callback();

            // Record success
            $this->recordSuccess($key, $state);

            return $result;
        } catch (\Exception $e) {
            // Record failure
            $this->recordFailure($key, $state);

            // Re-throw exception
            throw $e;
        }
    }

    /**
     * Get current state of circuit breaker
     */
    protected function getState(string $key): array
    {
        $state = $this->storage->getState($key);

        if (! $state) {
            return [
                'state' => State::CLOSED,
                'failure_count' => 0,
                'success_count' => 0,
                'last_failure_at' => null,
                'opened_at' => null,
            ];
        }

        return $state;
    }

    /**
     * Record a successful request
     */
    protected function recordSuccess(string $key, array $currentState): void
    {
        $this->storage->recordSuccess($key);

        // If in half-open state and we've had enough successes, close the circuit
        if ($currentState['state'] === State::HALF_OPEN) {
            $newState = $this->getState($key);
            if ($newState['success_count'] >= $this->halfOpenSuccessThreshold) {
                $this->transitionToClosed($key);
                Log::info('Circuit breaker closed after successful half-open requests', [
                    'key' => $key,
                    'success_count' => $newState['success_count'],
                ]);
            }
        }
    }

    /**
     * Record a failed request
     */
    protected function recordFailure(string $key, array $currentState): void
    {
        $failureCount = $this->storage->incrementFailure($key);

        // If in half-open state, immediately open the circuit
        if ($currentState['state'] === State::HALF_OPEN) {
            $this->transitionToOpen($key, $failureCount);
            Log::warning('Circuit breaker opened from half-open state', [
                'key' => $key,
                'failure_count' => $failureCount,
            ]);
        } elseif ($failureCount >= $this->failureThreshold) {
            // Threshold reached - open the circuit
            $this->transitionToOpen($key, $failureCount);
            Log::error('Circuit breaker opened - failure threshold reached', [
                'key' => $key,
                'failure_count' => $failureCount,
                'threshold' => $this->failureThreshold,
            ]);
        }
    }

    /**
     * Transition circuit to open state
     */
    protected function transitionToOpen(string $key, int $failureCount): void
    {
        $this->storage->setState($key, State::OPEN, $failureCount, time());
    }

    /**
     * Transition circuit to half-open state
     */
    protected function transitionToHalfOpen(string $key): void
    {
        $currentState = $this->getState($key);
        $this->storage->setState($key, State::HALF_OPEN, $currentState['failure_count']);
        Log::info('Circuit breaker transitioned to half-open', [
            'key' => $key,
        ]);
    }

    /**
     * Transition circuit to closed state
     */
    protected function transitionToClosed(string $key): void
    {
        $this->storage->resetFailure($key);
        Log::info('Circuit breaker closed', [
            'key' => $key,
        ]);
    }

    /**
     * Check if circuit should attempt half-open state
     */
    protected function shouldAttemptHalfOpen(string $key, array $state): bool
    {
        if ($state['state'] !== State::OPEN) {
            return false;
        }

        if (! $state['opened_at']) {
            return false;
        }

        $timeSinceOpen = time() - $state['opened_at']->getTimestamp();

        return $timeSinceOpen >= $this->timeout;
    }

    /**
     * Manually reset circuit breaker
     */
    public function reset(string $key): void
    {
        $this->storage->resetFailure($key);
        Log::info('Circuit breaker manually reset', [
            'key' => $key,
        ]);
    }

    /**
     * Get circuit breaker status
     */
    public function getStatus(string $key): array
    {
        return $this->getState($key);
    }

    /**
     * Get status for all circuit breakers in a domain
     *
     * @param  string  $domain  Failure domain (gateway, business, etc.)
     * @return array Map of identifier => status
     */
    public function getDomainStatus(string $domain): array
    {
        $statuses = [];
        $prefix = "{$domain}:";

        // Query storage for all keys with this domain prefix
        // This is implementation-specific - for database storage, we'd query the table
        if ($this->storage instanceof \App\Services\CircuitBreaker\DatabaseStorage) {
            $keys = \Illuminate\Support\Facades\DB::table('circuit_breaker_states')
                ->where('key', 'like', "{$prefix}%")
                ->pluck('key');

            foreach ($keys as $key) {
                $statuses[str_replace($prefix, '', $key)] = $this->getState($key);
            }
        }

        return $statuses;
    }

    /**
     * Execute with domain scoping (convenience method)
     *
     * @param  string  $domain  Failure domain
     * @param  string  $identifier  Domain identifier
     * @param  callable  $callback  Callback to execute
     * @param  callable|null  $fallback  Fallback callback
     * @return mixed Result from callback or fallback
     */
    public function executeWithDomain(string $domain, string $identifier, callable $callback, ?callable $fallback = null): mixed
    {
        $key = $this->buildKey($domain, $identifier);

        return $this->execute($key, $callback, $fallback);
    }
}
