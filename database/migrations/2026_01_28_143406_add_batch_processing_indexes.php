<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds optimized indexes for batch processing operations.
     * These indexes are critical for high-performance settlement window processing
     * and batch job queries.
     */
    public function up(): void
    {
        // Payment Jobs - Batch processing indexes
        if (Schema::hasTable('payment_jobs')) {
            Schema::table('payment_jobs', function (Blueprint $table) {
                // Index for settlement window batch processing
                // Covers: WHERE settlement_window_id = ? AND status = ? ORDER BY business_id
                if (! $this->hasIndex('payment_jobs', 'payment_jobs_settlement_status_business_idx')) {
                    $table->index(['settlement_window_id', 'status', 'payment_schedule_id'], 'payment_jobs_settlement_status_business_idx');
                }

                // Index for batch processing by business and status
                // Covers: WHERE status = ? AND payment_schedule_id IN (...) ORDER BY created_at
                if (! $this->hasIndex('payment_jobs', 'payment_jobs_status_schedule_created_idx')) {
                    $table->index(['status', 'payment_schedule_id', 'created_at'], 'payment_jobs_status_schedule_created_idx');
                }

                // Partial index for pending jobs (if database supports it)
                // For MySQL, we'll use a regular index since partial indexes aren't supported
                if (! $this->hasIndex('payment_jobs', 'payment_jobs_pending_idx')) {
                    $table->index(['status', 'created_at'], 'payment_jobs_pending_idx');
                }
            });
        }

        // Payroll Jobs - Batch processing indexes
        if (Schema::hasTable('payroll_jobs')) {
            Schema::table('payroll_jobs', function (Blueprint $table) {
                // Index for settlement window batch processing
                // Covers: WHERE settlement_window_id = ? AND status = ? ORDER BY business_id
                if (! $this->hasIndex('payroll_jobs', 'payroll_jobs_settlement_status_business_idx')) {
                    $table->index(['settlement_window_id', 'status', 'payroll_schedule_id'], 'payroll_jobs_settlement_status_business_idx');
                }

                // Index for batch processing by business and status
                // Covers: WHERE status = ? AND payroll_schedule_id IN (...) ORDER BY created_at
                if (! $this->hasIndex('payroll_jobs', 'payroll_jobs_status_schedule_created_idx')) {
                    $table->index(['status', 'payroll_schedule_id', 'created_at'], 'payroll_jobs_status_schedule_created_idx');
                }

                // Partial index for pending jobs
                if (! $this->hasIndex('payroll_jobs', 'payroll_jobs_pending_idx')) {
                    $table->index(['status', 'created_at'], 'payroll_jobs_pending_idx');
                }
            });
        }

        // Settlement Windows - Processing indexes
        if (Schema::hasTable('settlement_windows')) {
            Schema::table('settlement_windows', function (Blueprint $table) {
                // Index for finding pending windows to process
                if (! $this->hasIndex('settlement_windows', 'settlement_windows_status_created_idx')) {
                    $table->index(['status', 'created_at'], 'settlement_windows_status_created_idx');
                }

                // Index for window type and status queries
                if (! $this->hasIndex('settlement_windows', 'settlement_windows_type_status_idx')) {
                    $table->index(['window_type', 'status', 'window_start'], 'settlement_windows_type_status_idx');
                }
            });
        }

        // Payment Schedules - Business grouping index
        if (Schema::hasTable('payment_schedules')) {
            Schema::table('payment_schedules', function (Blueprint $table) {
                // Index for batch processing by business
                if (! $this->hasIndex('payment_schedules', 'payment_schedules_business_status_next_run_idx')) {
                    $table->index(['business_id', 'status', 'next_run_at'], 'payment_schedules_business_status_next_run_idx');
                }
            });
        }

        // Payroll Schedules - Business grouping index
        if (Schema::hasTable('payroll_schedules')) {
            Schema::table('payroll_schedules', function (Blueprint $table) {
                // Index for batch processing by business
                if (! $this->hasIndex('payroll_schedules', 'payroll_schedules_business_status_next_run_idx')) {
                    $table->index(['business_id', 'status', 'next_run_at'], 'payroll_schedules_business_status_next_run_idx');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('payment_jobs')) {
            Schema::table('payment_jobs', function (Blueprint $table) {
                $this->dropIndexIfExists($table, 'payment_jobs_settlement_status_business_idx');
                $this->dropIndexIfExists($table, 'payment_jobs_status_schedule_created_idx');
                $this->dropIndexIfExists($table, 'payment_jobs_pending_idx');
            });
        }

        if (Schema::hasTable('payroll_jobs')) {
            Schema::table('payroll_jobs', function (Blueprint $table) {
                $this->dropIndexIfExists($table, 'payroll_jobs_settlement_status_business_idx');
                $this->dropIndexIfExists($table, 'payroll_jobs_status_schedule_created_idx');
                $this->dropIndexIfExists($table, 'payroll_jobs_pending_idx');
            });
        }

        if (Schema::hasTable('settlement_windows')) {
            Schema::table('settlement_windows', function (Blueprint $table) {
                $this->dropIndexIfExists($table, 'settlement_windows_status_created_idx');
                $this->dropIndexIfExists($table, 'settlement_windows_type_status_idx');
            });
        }

        if (Schema::hasTable('payment_schedules')) {
            Schema::table('payment_schedules', function (Blueprint $table) {
                $this->dropIndexIfExists($table, 'payment_schedules_business_status_next_run_idx');
            });
        }

        if (Schema::hasTable('payroll_schedules')) {
            Schema::table('payroll_schedules', function (Blueprint $table) {
                $this->dropIndexIfExists($table, 'payroll_schedules_business_status_next_run_idx');
            });
        }
    }

    /**
     * Check if an index exists on a table.
     */
    private function hasIndex(string $table, string $indexName): bool
    {
        try {
            $indexes = Schema::getIndexes($table);

            foreach ($indexes as $index) {
                if ($index['name'] === $indexName) {
                    return true;
                }
            }
        } catch (\Exception $e) {
            // If we can't check, assume it doesn't exist and try to create it
            return false;
        }

        return false;
    }

    /**
     * Drop an index if it exists.
     */
    private function dropIndexIfExists(Blueprint $table, string $indexName): void
    {
        try {
            $table->dropIndex($indexName);
        } catch (\Exception $e) {
            // Ignore errors when dropping indexes (they may not exist)
        }
    }
};
