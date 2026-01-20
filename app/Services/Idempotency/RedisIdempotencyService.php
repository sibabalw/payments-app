<?php

namespace App\Services\Idempotency;

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;

class RedisIdempotencyService implements IdempotencyServiceInterface
{
    protected string $connection;
    protected string $prefix;

    public function __construct(?string $connection = null, ?string $prefix = null)
    {
        $this->connection = $connection ?? 'default';
        $this->prefix = $prefix ?? 'idempotency:';
    }

    /**
     * Execute a callback with idempotency protection.
     */
    public function execute(string $idempotencyKey, callable $callback, int $ttlSeconds = 86400)
    {
        try {
            $redis = Redis::connection($this->connection);
            $key = $this->prefix . $idempotencyKey;

            // Check if key exists
            $cached = $redis->get($key);

            if ($cached !== null) {
                Log::info('Idempotency key found in Redis, returning cached response', [
                    'idempotency_key' => $idempotencyKey,
                ]);

                return json_decode($cached, true);
            }

            // Execute callback and store result
            $result = $callback();

            $redis->setex($key, $ttlSeconds, json_encode($result));

            Log::info('Idempotency key created in Redis', [
                'idempotency_key' => $idempotencyKey,
                'ttl' => $ttlSeconds,
            ]);

            return $result;
        } catch (\Exception $e) {
            Log::error('Error executing idempotent operation with Redis', [
                'idempotency_key' => $idempotencyKey,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Check if an idempotency key exists and return cached response if available.
     */
    public function check(string $idempotencyKey)
    {
        try {
            $redis = Redis::connection($this->connection);
            $key = $this->prefix . $idempotencyKey;

            $cached = $redis->get($key);

            if ($cached !== null) {
                return json_decode($cached, true);
            }

            return null;
        } catch (\Exception $e) {
            Log::error('Error checking idempotency key in Redis', [
                'idempotency_key' => $idempotencyKey,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Record an idempotency key with a response.
     */
    public function record(string $idempotencyKey, $response, int $ttlSeconds = 86400): void
    {
        try {
            $redis = Redis::connection($this->connection);
            $key = $this->prefix . $idempotencyKey;

            $redis->setex($key, $ttlSeconds, json_encode($response));
        } catch (\Exception $e) {
            Log::error('Error recording idempotency key in Redis', [
                'idempotency_key' => $idempotencyKey,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
