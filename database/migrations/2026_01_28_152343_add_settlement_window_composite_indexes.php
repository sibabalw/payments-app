<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds composite indexes for settlement window queries to optimize batch processing.
     * These indexes are critical for high-performance settlement window processing.
     */
    public function up(): void
    {
        // Payment Jobs - Settlement window composite indexes
        if (Schema::hasTable('payment_jobs')) {
            Schema::table('payment_jobs', function (Blueprint $table) {
                // Index for settlement window batch processing with business pre-filtering
                // Covers: WHERE settlement_window_id = ? AND status = ? ORDER BY payment_schedule_id
                if (! $this->hasIndex('payment_jobs', 'payment_jobs_settlement_status_schedule_idx')) {
                    $table->index(['settlement_window_id', 'status', 'payment_schedule_id'], 'payment_jobs_settlement_status_schedule_idx');
                }

                // Index for settlement window queries with created_at for ordering
                // Covers: WHERE settlement_window_id = ? AND status = ? ORDER BY created_at
                if (! $this->hasIndex('payment_jobs', 'payment_jobs_settlement_status_created_idx')) {
                    $table->index(['settlement_window_id', 'status', 'created_at'], 'payment_jobs_settlement_status_created_idx');
                }
            });
        }

        // Payroll Jobs - Settlement window composite indexes
        if (Schema::hasTable('payroll_jobs')) {
            Schema::table('payroll_jobs', function (Blueprint $table) {
                // Index for settlement window batch processing with business pre-filtering
                // Covers: WHERE settlement_window_id = ? AND status = ? ORDER BY payroll_schedule_id
                if (! $this->hasIndex('payroll_jobs', 'payroll_jobs_settlement_status_schedule_idx')) {
                    $table->index(['settlement_window_id', 'status', 'payroll_schedule_id'], 'payroll_jobs_settlement_status_schedule_idx');
                }

                // Index for settlement window queries with created_at for ordering
                // Covers: WHERE settlement_window_id = ? AND status = ? ORDER BY created_at
                if (! $this->hasIndex('payroll_jobs', 'payroll_jobs_settlement_status_created_idx')) {
                    $table->index(['settlement_window_id', 'status', 'created_at'], 'payroll_jobs_settlement_status_created_idx');
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
                if ($this->hasIndex('payment_jobs', 'payment_jobs_settlement_status_schedule_idx')) {
                    $table->dropIndex('payment_jobs_settlement_status_schedule_idx');
                }
                if ($this->hasIndex('payment_jobs', 'payment_jobs_settlement_status_created_idx')) {
                    $table->dropIndex('payment_jobs_settlement_status_created_idx');
                }
            });
        }

        if (Schema::hasTable('payroll_jobs')) {
            Schema::table('payroll_jobs', function (Blueprint $table) {
                if ($this->hasIndex('payroll_jobs', 'payroll_jobs_settlement_status_schedule_idx')) {
                    $table->dropIndex('payroll_jobs_settlement_status_schedule_idx');
                }
                if ($this->hasIndex('payroll_jobs', 'payroll_jobs_settlement_status_created_idx')) {
                    $table->dropIndex('payroll_jobs_settlement_status_created_idx');
                }
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
};
