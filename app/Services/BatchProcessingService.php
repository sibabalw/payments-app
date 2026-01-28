<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BatchProcessingService
{
    /**
     * Process a batch of items with optimized database operations
     *
     * @param  Collection  $items  Items to process
     * @param  callable  $processor  Callback to process each item
     * @param  int  $batchSize  Number of items per batch
     * @param  bool  $useTransaction  Whether to wrap each batch in a transaction
     * @return array Results with 'processed', 'failed', and 'errors'
     */
    public function processBatch(
        Collection $items,
        callable $processor,
        int $batchSize = 100,
        bool $useTransaction = true
    ): array {
        $processed = 0;
        $failed = 0;
        $errors = [];

        $batches = $items->chunk($batchSize);

        foreach ($batches as $batch) {
            try {
                if ($useTransaction) {
                    DB::transaction(function () use ($batch, $processor, &$processed, &$failed, &$errors) {
                        $this->processBatchItems($batch, $processor, $processed, $failed, $errors);
                    });
                } else {
                    $this->processBatchItems($batch, $processor, $processed, $failed, $errors);
                }
            } catch (\Exception $e) {
                Log::error('Batch processing failed', [
                    'batch_size' => $batch->count(),
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                $failed += $batch->count();
                $errors[] = [
                    'batch' => 'unknown',
                    'error' => $e->getMessage(),
                ];
            }
        }

        return [
            'processed' => $processed,
            'failed' => $failed,
            'errors' => $errors,
            'total' => $items->count(),
        ];
    }

    /**
     * Process items in a batch
     */
    protected function processBatchItems(
        Collection $batch,
        callable $processor,
        int &$processed,
        int &$failed,
        array &$errors
    ): void {
        foreach ($batch as $item) {
            try {
                $result = $processor($item);
                if ($result !== false) {
                    $processed++;
                } else {
                    $failed++;
                }
            } catch (\Exception $e) {
                $failed++;
                $errors[] = [
                    'item_id' => $item->id ?? 'unknown',
                    'error' => $e->getMessage(),
                ];

                Log::warning('Item processing failed in batch', [
                    'item_id' => $item->id ?? 'unknown',
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Bulk insert records with optimized chunking
     *
     * @param  string  $table  Table name
     * @param  array  $records  Records to insert
     * @param  int  $chunkSize  Number of records per chunk
     * @return int Number of records inserted
     */
    public function bulkInsert(string $table, array $records, int $chunkSize = 500): int
    {
        if (empty($records)) {
            return 0;
        }

        $inserted = 0;
        $chunks = array_chunk($records, $chunkSize);

        foreach ($chunks as $chunk) {
            try {
                DB::table($table)->insert($chunk);
                $inserted += count($chunk);
            } catch (\Exception $e) {
                Log::error('Bulk insert failed', [
                    'table' => $table,
                    'chunk_size' => count($chunk),
                    'error' => $e->getMessage(),
                ]);

                throw $e;
            }
        }

        return $inserted;
    }

    /**
     * Group items by a key for batch processing
     *
     * @param  Collection  $items  Items to group
     * @param  string|callable  $key  Key to group by (field name or callback)
     * @return array Grouped items
     */
    public function groupBy(Collection $items, string|callable $key): array
    {
        $groups = [];

        foreach ($items as $item) {
            if (is_callable($key)) {
                $groupKey = $key($item);
            } else {
                $groupKey = $item->{$key};
            }

            if (! isset($groups[$groupKey])) {
                $groups[$groupKey] = collect();
            }

            $groups[$groupKey]->push($item);
        }

        return $groups;
    }

    /**
     * Pre-calculate balances for multiple businesses
     *
     * @param  Collection  $businesses  Businesses to calculate balances for
     * @return array Map of business_id => balance
     */
    public function preCalculateBalances(Collection $businesses, EscrowService $escrowService): array
    {
        $balances = [];

        foreach ($businesses as $business) {
            $balances[$business->id] = $escrowService->getAvailableBalance($business, false, false);
        }

        return $balances;
    }

    /**
     * Process items in parallel batches with controlled concurrency
     *
     * @param  Collection  $items  Items to process
     * @param  callable  $processor  Callback to process each item
     * @param  int  $batchSize  Number of items per batch
     * @param  int  $concurrency  Maximum number of parallel batches
     * @return array Results
     */
    public function processParallelBatches(
        Collection $items,
        callable $processor,
        int $batchSize = 100,
        int $concurrency = 3
    ): array {
        // For now, process sequentially but in optimized batches
        // Full parallel processing would require queue workers or async processing
        return $this->processBatch($items, $processor, $batchSize, true);
    }
}
