<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * High-performance sequence number generation service.
 *
 * Replaces MAX() queries with atomic increment operations for bank-grade performance.
 * Supports sequence pooling for batch operations to minimize database round-trips.
 */
class SequenceService
{
    /**
     * Pool of pre-allocated sequence numbers for batch operations
     */
    protected array $sequencePool = [];

    /**
     * Current pool size
     */
    protected int $poolSize = 0;

    /**
     * Minimum pool size before refilling
     */
    protected int $minPoolSize = 100;

    /**
     * Maximum pool size
     */
    protected int $maxPoolSize = 1000;

    /**
     * Get next sequence number (atomic operation)
     *
     * Uses atomic UPDATE to increment sequence without MAX() query bottleneck.
     * For MySQL: Uses LAST_INSERT_ID() pattern for atomic increment
     * For PostgreSQL: Uses RETURNING clause
     *
     * @return int Next sequence number
     */
    public function getNext(): int
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql' || $driver === 'mariadb') {
            // MySQL/MariaDB: Use atomic UPDATE with LAST_INSERT_ID pattern
            DB::statement('UPDATE ledger_sequence SET value = LAST_INSERT_ID(value + 1)');
            $sequence = DB::selectOne('SELECT LAST_INSERT_ID() as value');

            return (int) $sequence->value;
        } elseif ($driver === 'pgsql') {
            // PostgreSQL: Use UPDATE with RETURNING
            $result = DB::selectOne(
                'UPDATE ledger_sequence SET value = value + 1 RETURNING value'
            );

            return (int) $result->value;
        } else {
            // Fallback for other databases: use transaction with lock
            return DB::transaction(function () {
                $current = DB::table('ledger_sequence')
                    ->lockForUpdate()
                    ->value('value') ?? 0;

                $next = $current + 1;

                DB::table('ledger_sequence')
                    ->update(['value' => $next]);

                return $next;
            });
        }
    }

    /**
     * Get a range of sequence numbers for batch operations (sequence pooling)
     *
     * Pre-allocates a range of sequence numbers to minimize database round-trips
     * when processing bulk operations. This is critical for bank-grade performance.
     *
     * @param  int  $count  Number of sequence numbers needed
     * @return array Array of sequence numbers [start, start+1, ..., start+count-1]
     */
    public function getNextRange(int $count): array
    {
        if ($count <= 0) {
            return [];
        }

        $driver = DB::connection()->getDriverName();
        $start = null;

        if ($driver === 'mysql' || $driver === 'mariadb') {
            // MySQL: Atomic increment by count
            DB::statement("UPDATE ledger_sequence SET value = LAST_INSERT_ID(value + {$count})");
            $result = DB::selectOne('SELECT LAST_INSERT_ID() as value');
            $end = (int) $result->value;
            $start = $end - $count + 1;
        } elseif ($driver === 'pgsql') {
            // PostgreSQL: Atomic increment with RETURNING
            $result = DB::selectOne(
                "UPDATE ledger_sequence SET value = value + {$count} RETURNING value"
            );
            $end = (int) $result->value;
            $start = $end - $count + 1;
        } else {
            // Fallback: transaction with lock
            $start = DB::transaction(function () use ($count) {
                $current = DB::table('ledger_sequence')
                    ->lockForUpdate()
                    ->value('value') ?? 0;

                $next = $current + $count;

                DB::table('ledger_sequence')
                    ->update(['value' => $next]);

                return $current + 1;
            });
        }

        // Generate range array
        $range = [];
        for ($i = 0; $i < $count; $i++) {
            $range[] = $start + $i;
        }

        return $range;
    }

    /**
     * Get next sequence number from pool (faster for single operations)
     *
     * Uses internal pool to minimize database calls. Automatically refills
     * when pool is depleted.
     *
     * @return int Next sequence number
     */
    public function getNextFromPool(): int
    {
        if ($this->poolSize < $this->minPoolSize) {
            $this->refillPool();
        }

        $sequence = array_shift($this->sequencePool);
        $this->poolSize--;

        return $sequence;
    }

    /**
     * Refill the sequence pool
     */
    protected function refillPool(): void
    {
        $needed = $this->maxPoolSize - $this->poolSize;
        $range = $this->getNextRange($needed);

        $this->sequencePool = array_merge($this->sequencePool, $range);
        $this->poolSize = count($this->sequencePool);

        Log::debug('Sequence pool refilled', [
            'pool_size' => $this->poolSize,
        ]);
    }

    /**
     * Get current sequence value (without incrementing)
     *
     * @return int Current sequence value
     */
    public function getCurrent(): int
    {
        return (int) (DB::table('ledger_sequence')->value('value') ?? 0);
    }
}
