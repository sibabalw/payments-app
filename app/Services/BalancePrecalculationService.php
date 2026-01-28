<?php

namespace App\Services;

use App\Models\Business;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Balance Pre-calculation Service
 *
 * Pre-calculates balances for multiple businesses to reduce database queries.
 * Uses Redis caching for read-heavy operations to improve performance.
 */
class BalancePrecalculationService
{
    protected int $cacheTtl;

    public function __construct()
    {
        $this->cacheTtl = (int) config('payroll.balance_cache_ttl', 300); // 5 minutes default
    }

    /**
     * Get EscrowService instance (lazy-loaded to avoid circular dependency).
     */
    protected function getEscrowService(): EscrowService
    {
        return app(EscrowService::class);
    }

    /**
     * Pre-calculate balances for multiple businesses
     *
     * Loads balances for all businesses in a single query and caches them.
     * Critical for batch processing to avoid N+1 queries.
     *
     * @param  Collection|array  $businesses  Businesses or business IDs
     * @return array Map of business_id => available_balance
     */
    public function preCalculateBalances($businesses): array
    {
        $businessIds = $this->extractBusinessIds($businesses);

        if (empty($businessIds)) {
            return [];
        }

        $balances = [];
        $uncachedIds = [];

        // Try to get from cache first
        foreach ($businessIds as $businessId) {
            $cacheKey = $this->getCacheKey($businessId);
            $cached = Cache::get($cacheKey);

            if ($cached !== null) {
                $balances[$businessId] = (float) $cached;
            } else {
                $uncachedIds[] = $businessId;
            }
        }

        // Load uncached balances from database using read connection
        // Use single aggregated query for better performance (Laravel auto-routes to read replica)
        if (! empty($uncachedIds)) {
            // Use read connection explicitly for balance queries
            $connectionName = DB::connection()->getName();
            $businesses = Business::on($connectionName)
                ->whereIn('id', $uncachedIds)
                ->select(['id', 'escrow_balance', 'hold_amount'])
                ->get();

            foreach ($businesses as $business) {
                // Calculate available balance: posted balance - holds
                $postedBalance = (float) ($business->escrow_balance ?? 0);
                $holdAmount = (float) ($business->hold_amount ?? 0);
                $balance = $postedBalance - $holdAmount;

                $balances[$business->id] = $balance;

                // Cache the balance
                $this->cacheBalance($business->id, $balance);
            }
        }

        return $balances;
    }

    /**
     * Get balance for a single business (with caching)
     *
     * @param  Business|int  $business  Business model or ID
     * @return float Available balance
     */
    public function getBalance($business): float
    {
        $businessId = $business instanceof Business ? $business->id : (int) $business;
        $cacheKey = $this->getCacheKey($businessId);

        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($business) {
            $businessModel = $business instanceof Business ? $business : Business::findOrFail($business);

            return $this->getEscrowService()->getAvailableBalance($businessModel, false, false);
        });
    }

    /**
     * Invalidate balance cache for a business
     *
     * @param  Business|int  $business  Business model or ID
     */
    public function invalidateCache($business): void
    {
        $businessId = $business instanceof Business ? $business->id : (int) $business;
        $cacheKey = $this->getCacheKey($businessId);

        Cache::forget($cacheKey);
    }

    /**
     * Invalidate balance cache for multiple businesses
     *
     * @param  Collection|array  $businesses  Businesses or business IDs
     */
    public function invalidateCacheBulk($businesses): void
    {
        $businessIds = $this->extractBusinessIds($businesses);

        foreach ($businessIds as $businessId) {
            $this->invalidateCache($businessId);
        }
    }

    /**
     * Cache a balance value
     */
    protected function cacheBalance(int $businessId, float $balance): void
    {
        $cacheKey = $this->getCacheKey($businessId);
        Cache::put($cacheKey, $balance, $this->cacheTtl);
    }

    /**
     * Get cache key for a business
     */
    protected function getCacheKey(int $businessId): string
    {
        return "business_balance:{$businessId}";
    }

    /**
     * Extract business IDs from various input types
     */
    protected function extractBusinessIds($businesses): array
    {
        if ($businesses instanceof Collection) {
            return $businesses->pluck('id')->toArray();
        }

        if (is_array($businesses)) {
            return array_map(function ($business) {
                return $business instanceof Business ? $business->id : (int) $business;
            }, $businesses);
        }

        return [];
    }

    /**
     * Pre-validate jobs in batch (check balances, status, etc.)
     *
     * Validates all jobs before processing to avoid partial failures.
     *
     * @param  array  $paymentJobs  Array of PaymentJob models
     * @param  array  $payrollJobs  Array of PayrollJob models
     * @return array Validation results with 'valid' and 'invalid' arrays
     */
    public function preValidateJobs(array $paymentJobs = [], array $payrollJobs = []): array
    {
        $valid = ['payment' => [], 'payroll' => []];
        $invalid = ['payment' => [], 'payroll' => []];

        // Get all unique business IDs
        $businessIds = [];
        foreach ($paymentJobs as $job) {
            $businessIds[] = $job->paymentSchedule->business_id;
        }
        foreach ($payrollJobs as $job) {
            $businessIds[] = $job->payrollSchedule->business_id;
        }
        $businessIds = array_unique($businessIds);

        // Pre-calculate balances
        $balances = $this->preCalculateBalances($businessIds);

        // Validate payment jobs
        foreach ($paymentJobs as $job) {
            $businessId = $job->paymentSchedule->business_id;
            $availableBalance = $balances[$businessId] ?? 0;

            if ($job->status !== 'pending') {
                $invalid['payment'][] = [
                    'job' => $job,
                    'reason' => 'Invalid status: '.$job->status,
                ];
            } elseif ($availableBalance < $job->amount) {
                $invalid['payment'][] = [
                    'job' => $job,
                    'reason' => 'Insufficient balance',
                    'available' => $availableBalance,
                    'required' => $job->amount,
                ];
            } else {
                $valid['payment'][] = $job;
            }
        }

        // Validate payroll jobs
        foreach ($payrollJobs as $job) {
            $businessId = $job->payrollSchedule->business_id;
            $availableBalance = $balances[$businessId] ?? 0;

            if ($job->status !== 'pending') {
                $invalid['payroll'][] = [
                    'job' => $job,
                    'reason' => 'Invalid status: '.$job->status,
                ];
            } elseif ($availableBalance < $job->net_salary) {
                $invalid['payroll'][] = [
                    'job' => $job,
                    'reason' => 'Insufficient balance',
                    'available' => $availableBalance,
                    'required' => $job->net_salary,
                ];
            } else {
                $valid['payroll'][] = $job;
            }
        }

        return [
            'valid' => $valid,
            'invalid' => $invalid,
        ];
    }
}
