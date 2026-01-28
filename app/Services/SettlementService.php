<?php

namespace App\Services;

use App\Models\PaymentJob;
use App\Models\PayrollJob;
use Illuminate\Support\Facades\DB;

class SettlementService
{
    /**
     * Create or get current settlement window
     * Uses atomic operations to prevent race conditions
     */
    public function getCurrentWindow(string $windowType = 'hourly'): \stdClass
    {
        $windowDuration = $this->getWindowDuration($windowType);
        $windowStart = $this->getWindowStart($windowType);
        $windowEnd = $windowStart->copy()->add($windowDuration);

        // Use transaction with retry logic to handle concurrent window creation
        return DB::transaction(function () use ($windowType, $windowStart, $windowEnd) {
            // Try to find existing window with lock to prevent concurrent creation
            $window = DB::table('settlement_windows')
                ->where('window_type', $windowType)
                ->where('window_start', $windowStart)
                ->where('window_end', $windowEnd)
                ->where('status', 'pending')
                ->lockForUpdate()
                ->first();

            if ($window) {
                return $window;
            }

            // Window doesn't exist - create it atomically
            // Use try-catch to handle race condition if another process created it
            try {
                $windowId = DB::table('settlement_windows')->insertGetId([
                    'window_type' => $windowType,
                    'window_start' => $windowStart,
                    'window_end' => $windowEnd,
                    'status' => 'pending',
                    'transaction_count' => 0,
                    'total_amount' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                return DB::table('settlement_windows')->find($windowId);
            } catch (\Illuminate\Database\QueryException $e) {
                // Handle duplicate key error (if unique constraint exists) or race condition
                // Retry select in case another process created it
                if ($e->getCode() === '23000' || str_contains($e->getMessage(), 'Duplicate entry')) {
                    $window = DB::table('settlement_windows')
                        ->where('window_type', $windowType)
                        ->where('window_start', $windowStart)
                        ->where('window_end', $windowEnd)
                        ->where('status', 'pending')
                        ->first();

                    if ($window) {
                        return $window;
                    }
                }

                // Re-throw if it's not a duplicate key error
                throw $e;
            }
        });
    }

    /**
     * Get window duration based on type
     */
    protected function getWindowDuration(string $windowType): \DateInterval
    {
        return match ($windowType) {
            'hourly' => new \DateInterval('PT1H'),
            'daily' => new \DateInterval('P1D'),
            'custom' => new \DateInterval('PT4H'), // 4 hours default
            default => new \DateInterval('PT1H'),
        };
    }

    /**
     * Get window start time based on type
     */
    protected function getWindowStart(string $windowType): \Carbon\Carbon
    {
        $now = now();

        return match ($windowType) {
            'hourly' => $now->copy()->startOfHour(),
            'daily' => $now->copy()->startOfDay(),
            'custom' => $now->copy()->setTime((int) ($now->hour / 4) * 4, 0, 0), // Every 4 hours
            default => $now->copy()->startOfHour(),
        };
    }

    /**
     * Assign payment job to settlement window
     */
    public function assignPaymentJob(PaymentJob $paymentJob, string $windowType = 'hourly'): void
    {
        $window = $this->getCurrentWindow($windowType);

        DB::table('payment_jobs')
            ->where('id', $paymentJob->id)
            ->update([
                'settlement_window_id' => $window->id,
                'updated_at' => now(),
            ]);

        DB::table('settlement_windows')
            ->where('id', $window->id)
            ->increment('transaction_count');

        DB::table('settlement_windows')
            ->where('id', $window->id)
            ->increment('total_amount', $paymentJob->amount);
    }

    /**
     * Assign payroll job to settlement window
     */
    public function assignPayrollJob(PayrollJob $payrollJob, string $windowType = 'hourly'): void
    {
        $window = $this->getCurrentWindow($windowType);

        DB::table('payroll_jobs')
            ->where('id', $payrollJob->id)
            ->update([
                'settlement_window_id' => $window->id,
                'updated_at' => now(),
            ]);

        DB::table('settlement_windows')
            ->where('id', $window->id)
            ->increment('transaction_count');

        DB::table('settlement_windows')
            ->where('id', $window->id)
            ->increment('total_amount', $payrollJob->net_salary);
    }

    /**
     * Batch assign payment jobs to settlement window
     *
     * @param  array  $paymentJobIds  Array of payment job IDs
     * @param  string  $windowType  Window type (default: 'hourly')
     */
    public function assignPaymentJobsBulk(array $paymentJobIds, string $windowType = 'hourly'): void
    {
        if (empty($paymentJobIds)) {
            return;
        }

        $window = $this->getCurrentWindow($windowType);

        // Calculate total amount for all jobs in a single query
        $totalAmount = DB::table('payment_jobs')
            ->whereIn('id', $paymentJobIds)
            ->sum('amount');

        // Bulk update all jobs to the settlement window
        DB::table('payment_jobs')
            ->whereIn('id', $paymentJobIds)
            ->update([
                'settlement_window_id' => $window->id,
                'updated_at' => now(),
            ]);

        // Update window statistics in bulk
        DB::table('settlement_windows')
            ->where('id', $window->id)
            ->increment('transaction_count', count($paymentJobIds));

        DB::table('settlement_windows')
            ->where('id', $window->id)
            ->increment('total_amount', $totalAmount);
    }

    /**
     * Batch assign payroll jobs to settlement window
     *
     * @param  array  $payrollJobIds  Array of payroll job IDs
     * @param  string  $windowType  Window type (default: 'hourly')
     */
    public function assignPayrollJobsBulk(array $payrollJobIds, string $windowType = 'hourly'): void
    {
        if (empty($payrollJobIds)) {
            return;
        }

        $window = $this->getCurrentWindow($windowType);

        // Calculate total amount for all jobs in a single query
        $totalAmount = DB::table('payroll_jobs')
            ->whereIn('id', $payrollJobIds)
            ->sum('net_salary');

        // Bulk update all jobs to the settlement window
        DB::table('payroll_jobs')
            ->whereIn('id', $payrollJobIds)
            ->update([
                'settlement_window_id' => $window->id,
                'updated_at' => now(),
            ]);

        // Update window statistics in bulk
        DB::table('settlement_windows')
            ->where('id', $window->id)
            ->increment('transaction_count', count($payrollJobIds));

        DB::table('settlement_windows')
            ->where('id', $window->id)
            ->increment('total_amount', $totalAmount);
    }

    /**
     * Process a settlement window
     *
     * @deprecated Use SettlementBatchService::processWindow() or ProcessSettlementWindowJob for batch processing
     */
    public function processWindow(int $windowId): bool
    {
        // Delegate to batch service for high-performance processing
        $batchService = app(SettlementBatchService::class);
        $result = $batchService->processWindow($windowId);

        // Mark window as settled
        DB::table('settlement_windows')
            ->where('id', $windowId)
            ->update([
                'status' => 'settled',
                'settled_at' => now(),
            ]);

        return true;
    }
}
