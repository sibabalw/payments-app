<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds missing composite indexes for common payroll query patterns.
     */
    public function up(): void
    {
        Schema::table('payroll_jobs', function (Blueprint $table) {
            // Index for querying permanently failed jobs (dead letter queue)
            if (! $this->hasIndex('payroll_jobs', 'payroll_jobs_permanently_failed_idx')) {
                $table->index(['status', 'permanently_failed_at'], 'payroll_jobs_permanently_failed_idx');
            }

            // Index for reconciliation queries (status + calculation_version)
            if (! $this->hasIndex('payroll_jobs', 'payroll_jobs_status_version_idx')) {
                $table->index(['status', 'calculation_version'], 'payroll_jobs_status_version_idx');
            }

            // Index for period-based queries with status
            if (! $this->hasIndex('payroll_jobs', 'payroll_jobs_period_status_created_idx')) {
                $table->index(['pay_period_start', 'pay_period_end', 'status', 'created_at'], 'payroll_jobs_period_status_created_idx');
            }
        });

        Schema::table('payroll_schedules', function (Blueprint $table) {
            // Index for due schedules query (already exists but ensure it's optimal)
            if (! $this->hasIndex('payroll_schedules', 'payroll_schedules_due_idx')) {
                $table->index(['status', 'next_run_at'], 'payroll_schedules_due_idx');
            }

            // Index for business + status queries
            if (! $this->hasIndex('payroll_schedules', 'payroll_schedules_business_status_idx')) {
                $table->index(['business_id', 'status', 'next_run_at'], 'payroll_schedules_business_status_idx');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payroll_jobs', function (Blueprint $table) {
            $table->dropIndex('payroll_jobs_permanently_failed_idx');
            $table->dropIndex('payroll_jobs_status_version_idx');
            $table->dropIndex('payroll_jobs_period_status_created_idx');
        });

        Schema::table('payroll_schedules', function (Blueprint $table) {
            $table->dropIndex('payroll_schedules_due_idx');
            $table->dropIndex('payroll_schedules_business_status_idx');
        });
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
