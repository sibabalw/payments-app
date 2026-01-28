<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Bulk Balance Update Service
 *
 * Provides high-performance batch balance updates using CASE statements.
 * Eliminates N+1 update queries by updating multiple business balances in a single SQL statement.
 * Critical for bank-grade performance when processing large batches of transactions.
 */
class BulkBalanceUpdateService
{
    /**
     * Update multiple business balances atomically in a single query
     *
     * Uses CASE statement pattern: UPDATE businesses SET escrow_balance = escrow_balance + CASE id WHEN ? THEN ? ... END WHERE id IN (...)
     * This is significantly faster than individual UPDATE statements under load.
     *
     * @param  array  $updates  Array of ['business_id' => int, 'amount' => float] pairs
     *                          Positive amounts increment, negative amounts decrement
     * @return int Number of rows affected
     */
    public function updateBalances(array $updates): int
    {
        if (empty($updates)) {
            return 0;
        }

        return DB::transaction(function () use ($updates) {
            // Group updates by business_id (sum amounts if same business appears multiple times)
            $grouped = [];
            foreach ($updates as $update) {
                $businessId = (int) $update['business_id'];
                $amount = (float) $update['amount'];

                if (! isset($grouped[$businessId])) {
                    $grouped[$businessId] = 0;
                }

                $grouped[$businessId] += $amount;
            }

            // Build CASE statement for MySQL/PostgreSQL
            $driver = DB::connection()->getDriverName();
            $businessIds = array_keys($grouped);

            // Chunk large updates to avoid query size limits (500 businesses per chunk)
            // This allows processing thousands of businesses efficiently
            $chunkSize = 500;
            $totalUpdated = 0;

            $chunks = array_chunk($businessIds, $chunkSize, true);
            foreach ($chunks as $chunk) {
                $chunkGrouped = array_intersect_key($grouped, array_flip($chunk));

                if ($driver === 'mysql' || $driver === 'mariadb') {
                    $totalUpdated += $this->updateBalancesMySQL($chunk, $chunkGrouped);
                } elseif ($driver === 'pgsql') {
                    $totalUpdated += $this->updateBalancesPostgreSQL($chunk, $chunkGrouped);
                } else {
                    // Fallback: use individual updates in transaction
                    $totalUpdated += $this->updateBalancesFallback($chunkGrouped);
                }
            }

            // Invalidate cache for all updated businesses after transaction commits
            DB::afterCommit(function () use ($businessIds) {
                $balancePrecalculationService = app(\App\Services\BalancePrecalculationService::class);
                $balancePrecalculationService->invalidateCacheBulk($businessIds);
            });

            return $totalUpdated;
        });
    }

    /**
     * Update balances using MySQL CASE statement
     */
    protected function updateBalancesMySQL(array $businessIds, array $amounts): int
    {
        $cases = [];
        $bindings = [];

        foreach ($amounts as $businessId => $amount) {
            $cases[] = 'WHEN ? THEN escrow_balance + ?';
            $bindings[] = $businessId;
            $bindings[] = $amount;
        }

        $caseStatement = implode(' ', $cases);
        $placeholders = implode(',', array_fill(0, count($businessIds), '?'));

        $sql = "UPDATE businesses 
                SET escrow_balance = CASE id 
                    {$caseStatement}
                END,
                updated_at = NOW()
                WHERE id IN ({$placeholders})";

        $allBindings = array_merge($bindings, $businessIds);

        return DB::update($sql, $allBindings);
    }

    /**
     * Update balances using PostgreSQL CASE statement
     */
    protected function updateBalancesPostgreSQL(array $businessIds, array $amounts): int
    {
        $cases = [];
        $bindings = [];

        foreach ($amounts as $businessId => $amount) {
            $cases[] = 'WHEN ? THEN escrow_balance + ?';
            $bindings[] = $businessId;
            $bindings[] = $amount;
        }

        $caseStatement = implode(' ', $cases);
        $placeholders = implode(',', array_fill(0, count($businessIds), '?'));

        $sql = "UPDATE businesses 
                SET escrow_balance = CASE id 
                    {$caseStatement}
                END,
                updated_at = NOW()
                WHERE id IN ({$placeholders})";

        $allBindings = array_merge($bindings, $businessIds);

        return DB::update($sql, $allBindings);
    }

    /**
     * Fallback: individual updates in transaction (for unsupported databases)
     */
    protected function updateBalancesFallback(array $amounts): int
    {
        $updated = 0;

        foreach ($amounts as $businessId => $amount) {
            $affected = DB::table('businesses')
                ->where('id', $businessId)
                ->increment('escrow_balance', $amount);

            $updated += $affected;
        }

        return $updated;
    }

    /**
     * Increment balances for multiple businesses
     *
     * @param  array  $increments  Array of ['business_id' => int, 'amount' => float]
     * @return int Number of rows affected
     */
    public function incrementBalances(array $increments): int
    {
        return $this->updateBalances($increments);
    }

    /**
     * Decrement balances for multiple businesses
     *
     * @param  array  $decrements  Array of ['business_id' => int, 'amount' => float]
     * @return int Number of rows affected
     */
    public function decrementBalances(array $decrements): int
    {
        // Convert to increments with negative amounts
        $updates = array_map(function ($decrement) {
            return [
                'business_id' => $decrement['business_id'],
                'amount' => -abs($decrement['amount']), // Ensure negative
            ];
        }, $decrements);

        return $this->updateBalances($updates);
    }

    /**
     * Set balances for multiple businesses (absolute values)
     *
     * @param  array  $balances  Array of ['business_id' => int, 'balance' => float]
     * @return int Number of rows affected
     */
    public function setBalances(array $balances): int
    {
        if (empty($balances)) {
            return 0;
        }

        return DB::transaction(function () use ($balances) {
            $driver = DB::connection()->getDriverName();

            // Chunk large updates to avoid query size limits (500 businesses per chunk)
            $chunkSize = 500;
            $totalUpdated = 0;

            $chunks = array_chunk($balances, $chunkSize);
            $allBusinessIds = [];
            foreach ($chunks as $chunk) {
                $businessIds = array_column($chunk, 'business_id');
                $allBusinessIds = array_merge($allBusinessIds, $businessIds);

                if ($driver === 'mysql' || $driver === 'mariadb') {
                    $totalUpdated += $this->setBalancesMySQL($businessIds, $chunk);
                } elseif ($driver === 'pgsql') {
                    $totalUpdated += $this->setBalancesPostgreSQL($businessIds, $chunk);
                } else {
                    $totalUpdated += $this->setBalancesFallback($chunk);
                }
            }

            // Invalidate cache for all updated businesses after transaction commits
            DB::afterCommit(function () use ($allBusinessIds) {
                $balancePrecalculationService = app(\App\Services\BalancePrecalculationService::class);
                $balancePrecalculationService->invalidateCacheBulk(array_unique($allBusinessIds));
            });

            return $totalUpdated;
        });
    }

    /**
     * Set balances using MySQL CASE statement
     */
    protected function setBalancesMySQL(array $businessIds, array $balances): int
    {
        $cases = [];
        $bindings = [];

        foreach ($balances as $balance) {
            $cases[] = 'WHEN ? THEN ?';
            $bindings[] = $balance['business_id'];
            $bindings[] = $balance['balance'];
        }

        $caseStatement = implode(' ', $cases);
        $placeholders = implode(',', array_fill(0, count($businessIds), '?'));

        $sql = "UPDATE businesses 
                SET escrow_balance = CASE id 
                    {$caseStatement}
                END,
                updated_at = NOW()
                WHERE id IN ({$placeholders})";

        $allBindings = array_merge($bindings, $businessIds);

        return DB::update($sql, $allBindings);
    }

    /**
     * Set balances using PostgreSQL CASE statement
     */
    protected function setBalancesPostgreSQL(array $businessIds, array $balances): int
    {
        $cases = [];
        $bindings = [];

        foreach ($balances as $balance) {
            $cases[] = 'WHEN ? THEN ?';
            $bindings[] = $balance['business_id'];
            $bindings[] = $balance['balance'];
        }

        $caseStatement = implode(' ', $cases);
        $placeholders = implode(',', array_fill(0, count($businessIds), '?'));

        $sql = "UPDATE businesses 
                SET escrow_balance = CASE id 
                    {$caseStatement}
                END,
                updated_at = NOW()
                WHERE id IN ({$placeholders})";

        $allBindings = array_merge($bindings, $businessIds);

        return DB::update($sql, $allBindings);
    }

    /**
     * Fallback: individual updates
     */
    protected function setBalancesFallback(array $balances): int
    {
        $updated = 0;

        foreach ($balances as $balance) {
            $affected = DB::table('businesses')
                ->where('id', $balance['business_id'])
                ->update([
                    'escrow_balance' => $balance['balance'],
                    'updated_at' => now(),
                ]);

            $updated += $affected;
        }

        return $updated;
    }
}
