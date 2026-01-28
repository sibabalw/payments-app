<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Bulk Job Update Service
 *
 * Provides high-performance bulk job status updates using SQL CASE statements.
 * Eliminates N+1 update queries by updating multiple job statuses in a single SQL statement.
 * Critical for bank-grade performance when processing large batches of transactions.
 */
class BulkJobUpdateService
{
    /**
     * Bulk update payment job statuses
     *
     * Uses CASE statement pattern: UPDATE payment_jobs SET status = CASE id WHEN ? THEN ? ... END WHERE id IN (...)
     * This is significantly faster than individual UPDATE statements under load.
     *
     * @param  array  $updates  Array of ['job_id' => int, 'status' => string, 'error_message' => string|null, 'transaction_id' => string|null]
     * @return int Number of rows affected
     */
    public function updatePaymentJobStatuses(array $updates): int
    {
        if (empty($updates)) {
            return 0;
        }

        return DB::transaction(function () use ($updates) {
            $driver = DB::connection()->getDriverName();
            $jobIds = array_column($updates, 'job_id');

            if ($driver === 'mysql' || $driver === 'mariadb') {
                return $this->updatePaymentJobStatusesMySQL($jobIds, $updates);
            } elseif ($driver === 'pgsql') {
                return $this->updatePaymentJobStatusesPostgreSQL($jobIds, $updates);
            } else {
                return $this->updatePaymentJobStatusesFallback($updates);
            }
        });
    }

    /**
     * Update payment job statuses using MySQL CASE statement
     */
    protected function updatePaymentJobStatusesMySQL(array $jobIds, array $updates): int
    {
        $statusCases = [];
        $errorCases = [];
        $transactionCases = [];
        $processedAtCases = [];
        $bindings = [];

        foreach ($updates as $update) {
            $jobId = (int) $update['job_id'];
            $status = $update['status'] ?? 'pending';
            $errorMessage = $update['error_message'] ?? null;
            $transactionId = $update['transaction_id'] ?? null;
            $processedAt = $update['processed_at'] ?? now();

            $statusCases[] = 'WHEN ? THEN ?';
            $bindings[] = $jobId;
            $bindings[] = $status;

            if ($errorMessage !== null) {
                $errorCases[] = 'WHEN ? THEN ?';
                $bindings[] = $jobId;
                $bindings[] = $errorMessage;
            }

            if ($transactionId !== null) {
                $transactionCases[] = 'WHEN ? THEN ?';
                $bindings[] = $jobId;
                $bindings[] = $transactionId;
            }

            $processedAtCases[] = 'WHEN ? THEN ?';
            $bindings[] = $jobId;
            $bindings[] = $processedAt;
        }

        $statusCaseStatement = implode(' ', $statusCases);
        $errorCaseStatement = ! empty($errorCases) ? implode(' ', $errorCases) : null;
        $transactionCaseStatement = ! empty($transactionCases) ? implode(' ', $transactionCases) : null;
        $processedAtCaseStatement = implode(' ', $processedAtCases);

        $placeholders = implode(',', array_fill(0, count($jobIds), '?'));

        $setClauses = ["status = CASE id {$statusCaseStatement} END"];
        $setClauses[] = "processed_at = CASE id {$processedAtCaseStatement} END";

        if ($errorCaseStatement) {
            $setClauses[] = "error_message = CASE id {$errorCaseStatement} END";
        }

        if ($transactionCaseStatement) {
            $setClauses[] = "transaction_id = CASE id {$transactionCaseStatement} END";
        }

        $setClause = implode(', ', $setClauses);

        $sql = "UPDATE payment_jobs 
                SET {$setClause},
                updated_at = NOW()
                WHERE id IN ({$placeholders})";

        $allBindings = array_merge($bindings, $jobIds);

        return DB::update($sql, $allBindings);
    }

    /**
     * Update payment job statuses using PostgreSQL CASE statement
     */
    protected function updatePaymentJobStatusesPostgreSQL(array $jobIds, array $updates): int
    {
        // PostgreSQL syntax is similar to MySQL for CASE statements
        return $this->updatePaymentJobStatusesMySQL($jobIds, $updates);
    }

    /**
     * Fallback: individual updates in transaction
     */
    protected function updatePaymentJobStatusesFallback(array $updates): int
    {
        $updated = 0;

        foreach ($updates as $update) {
            $data = [
                'status' => $update['status'] ?? 'pending',
                'processed_at' => $update['processed_at'] ?? now(),
                'updated_at' => now(),
            ];

            if (isset($update['error_message'])) {
                $data['error_message'] = $update['error_message'];
            }

            if (isset($update['transaction_id'])) {
                $data['transaction_id'] = $update['transaction_id'];
            }

            $affected = DB::table('payment_jobs')
                ->where('id', $update['job_id'])
                ->update($data);

            $updated += $affected;
        }

        return $updated;
    }

    /**
     * Bulk update payroll job statuses
     *
     * @param  array  $updates  Array of ['job_id' => int, 'status' => string, 'error_message' => string|null, 'transaction_id' => string|null]
     * @return int Number of rows affected
     */
    public function updatePayrollJobStatuses(array $updates): int
    {
        if (empty($updates)) {
            return 0;
        }

        return DB::transaction(function () use ($updates) {
            $driver = DB::connection()->getDriverName();
            $jobIds = array_column($updates, 'job_id');

            if ($driver === 'mysql' || $driver === 'mariadb') {
                return $this->updatePayrollJobStatusesMySQL($jobIds, $updates);
            } elseif ($driver === 'pgsql') {
                return $this->updatePayrollJobStatusesPostgreSQL($jobIds, $updates);
            } else {
                return $this->updatePayrollJobStatusesFallback($updates);
            }
        });
    }

    /**
     * Update payroll job statuses using MySQL CASE statement
     */
    protected function updatePayrollJobStatusesMySQL(array $jobIds, array $updates): int
    {
        $statusCases = [];
        $errorCases = [];
        $transactionCases = [];
        $processedAtCases = [];
        $bindings = [];

        foreach ($updates as $update) {
            $jobId = (int) $update['job_id'];
            $status = $update['status'] ?? 'pending';
            $errorMessage = $update['error_message'] ?? null;
            $transactionId = $update['transaction_id'] ?? null;
            $processedAt = $update['processed_at'] ?? now();

            $statusCases[] = 'WHEN ? THEN ?';
            $bindings[] = $jobId;
            $bindings[] = $status;

            if ($errorMessage !== null) {
                $errorCases[] = 'WHEN ? THEN ?';
                $bindings[] = $jobId;
                $bindings[] = $errorMessage;
            }

            if ($transactionId !== null) {
                $transactionCases[] = 'WHEN ? THEN ?';
                $bindings[] = $jobId;
                $bindings[] = $transactionId;
            }

            $processedAtCases[] = 'WHEN ? THEN ?';
            $bindings[] = $jobId;
            $bindings[] = $processedAt;
        }

        $statusCaseStatement = implode(' ', $statusCases);
        $errorCaseStatement = ! empty($errorCases) ? implode(' ', $errorCases) : null;
        $transactionCaseStatement = ! empty($transactionCases) ? implode(' ', $transactionCases) : null;
        $processedAtCaseStatement = implode(' ', $processedAtCases);

        $placeholders = implode(',', array_fill(0, count($jobIds), '?'));

        $setClauses = ["status = CASE id {$statusCaseStatement} END"];
        $setClauses[] = "processed_at = CASE id {$processedAtCaseStatement} END";

        if ($errorCaseStatement) {
            $setClauses[] = "error_message = CASE id {$errorCaseStatement} END";
        }

        if ($transactionCaseStatement) {
            $setClauses[] = "transaction_id = CASE id {$transactionCaseStatement} END";
        }

        $setClause = implode(', ', $setClauses);

        $sql = "UPDATE payroll_jobs 
                SET {$setClause},
                updated_at = NOW()
                WHERE id IN ({$placeholders})";

        $allBindings = array_merge($bindings, $jobIds);

        return DB::update($sql, $allBindings);
    }

    /**
     * Update payroll job statuses using PostgreSQL CASE statement
     */
    protected function updatePayrollJobStatusesPostgreSQL(array $jobIds, array $updates): int
    {
        return $this->updatePayrollJobStatusesMySQL($jobIds, $updates);
    }

    /**
     * Fallback: individual updates
     */
    protected function updatePayrollJobStatusesFallback(array $updates): int
    {
        $updated = 0;

        foreach ($updates as $update) {
            $data = [
                'status' => $update['status'] ?? 'pending',
                'processed_at' => $update['processed_at'] ?? now(),
                'updated_at' => now(),
            ];

            if (isset($update['error_message'])) {
                $data['error_message'] = $update['error_message'];
            }

            if (isset($update['transaction_id'])) {
                $data['transaction_id'] = $update['transaction_id'];
            }

            $affected = DB::table('payroll_jobs')
                ->where('id', $update['job_id'])
                ->update($data);

            $updated += $affected;
        }

        return $updated;
    }

    /**
     * Mark multiple payment jobs as succeeded
     *
     * @param  array  $jobIds  Array of job IDs
     * @param  string|null  $transactionIdPrefix  Prefix for transaction IDs (optional)
     * @return int Number of rows affected
     */
    public function markPaymentJobsAsSucceeded(array $jobIds, ?string $transactionIdPrefix = null): int
    {
        if (empty($jobIds)) {
            return 0;
        }

        $now = now();
        $updates = [];

        foreach ($jobIds as $jobId) {
            $update = [
                'job_id' => $jobId,
                'status' => 'succeeded',
                'processed_at' => $now,
            ];

            if ($transactionIdPrefix) {
                $update['transaction_id'] = $transactionIdPrefix.'-'.$now->format('YmdHis').'-'.$jobId;
            }

            $updates[] = $update;
        }

        return $this->updatePaymentJobStatuses($updates);
    }

    /**
     * Mark multiple payroll jobs as succeeded
     *
     * @param  array  $jobIds  Array of job IDs
     * @param  string|null  $transactionIdPrefix  Prefix for transaction IDs (optional)
     * @return int Number of rows affected
     */
    public function markPayrollJobsAsSucceeded(array $jobIds, ?string $transactionIdPrefix = null): int
    {
        if (empty($jobIds)) {
            return 0;
        }

        $now = now();
        $updates = [];

        foreach ($jobIds as $jobId) {
            $update = [
                'job_id' => $jobId,
                'status' => 'succeeded',
                'processed_at' => $now,
            ];

            if ($transactionIdPrefix) {
                $update['transaction_id'] = $transactionIdPrefix.'-'.$now->format('YmdHis').'-'.$jobId;
            }

            $updates[] = $update;
        }

        return $this->updatePayrollJobStatuses($updates);
    }

    /**
     * Mark multiple jobs as failed
     *
     * @param  array  $failures  Array of ['job_id' => int, 'error_message' => string]
     * @param  string  $type  'payment' or 'payroll'
     * @return int Number of rows affected
     */
    public function markJobsAsFailed(array $failures, string $type = 'payment'): int
    {
        if (empty($failures)) {
            return 0;
        }

        $now = now();
        $updates = [];

        foreach ($failures as $failure) {
            $updates[] = [
                'job_id' => $failure['job_id'],
                'status' => 'failed',
                'error_message' => $failure['error_message'] ?? 'Processing failed',
                'processed_at' => $now,
            ];
        }

        if ($type === 'payroll') {
            return $this->updatePayrollJobStatuses($updates);
        }

        return $this->updatePaymentJobStatuses($updates);
    }
}
