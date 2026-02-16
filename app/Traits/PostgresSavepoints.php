<?php

namespace App\Traits;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * PostgreSQL Savepoints Trait
 *
 * Provides savepoint support for nested transactions in PostgreSQL.
 * Allows partial rollback within a transaction.
 */
trait PostgresSavepoints
{
    /**
     * Execute a callback with a savepoint (nested transaction)
     *
     * @param  string  $savepointName  Name of the savepoint
     * @param  callable  $callback  Callback to execute
     * @return mixed Result from callback
     *
     * @throws \Exception If callback fails, savepoint is rolled back
     */
    protected function withSavepoint(string $savepointName, callable $callback)
    {
        $driver = DB::connection()->getDriverName();

        // Only use savepoints in PostgreSQL
        if ($driver !== 'pgsql') {
            // For other databases, just execute the callback
            // MySQL doesn't support savepoints in the same way
            return $callback();
        }

        try {
            // Create savepoint
            DB::statement("SAVEPOINT {$savepointName}");

            try {
                $result = $callback();

                // Release savepoint on success
                DB::statement("RELEASE SAVEPOINT {$savepointName}");

                return $result;
            } catch (\Exception $e) {
                // Rollback to savepoint on error
                DB::statement("ROLLBACK TO SAVEPOINT {$savepointName}");

                Log::warning('Transaction rolled back to savepoint', [
                    'savepoint' => $savepointName,
                    'error' => $e->getMessage(),
                ]);

                throw $e;
            }
        } catch (\Exception $e) {
            // If savepoint creation fails, log and rethrow
            Log::error('Failed to create savepoint', [
                'savepoint' => $savepointName,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Execute multiple callbacks with individual savepoints
     *
     * @param  array  $callbacks  Array of ['name' => string, 'callback' => callable]
     * @return array Results from callbacks
     */
    protected function withSavepoints(array $callbacks): array
    {
        $results = [];
        $driver = DB::connection()->getDriverName();

        // Only use savepoints in PostgreSQL
        if ($driver !== 'pgsql') {
            // For other databases, execute sequentially
            foreach ($callbacks as $item) {
                $results[$item['name']] = $item['callback']();
            }

            return $results;
        }

        foreach ($callbacks as $item) {
            $name = $item['name'];
            $callback = $item['callback'];

            try {
                $results[$name] = $this->withSavepoint($name, $callback);
            } catch (\Exception $e) {
                // Continue with other callbacks even if one fails
                $results[$name] = ['error' => $e->getMessage()];
                Log::warning('Savepoint callback failed, continuing with others', [
                    'savepoint' => $name,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $results;
    }
}
