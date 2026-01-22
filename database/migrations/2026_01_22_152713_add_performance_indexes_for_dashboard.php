<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * These indexes are critical for dashboard performance under load.
     * They optimize:
     * - Time-series aggregation queries (trends, charts)
     * - Business filtering with JOINs
     * - Status-based filtering with date ranges
     */
    public function up(): void
    {
        // Payment Jobs - Critical composite indexes for dashboard queries
        Schema::table('payment_jobs', function (Blueprint $table) {
            // For time-series queries: WHERE status = 'succeeded' AND processed_at >= ?
            // Covers: daily trends, weekly trends, monthly trends, success rates
            $table->index(['status', 'processed_at'], 'pj_status_processed_at_idx');

            // For filtering by processed_at alone (range scans)
            $table->index(['processed_at'], 'pj_processed_at_idx');

            // For queries that filter status + aggregate by date
            // Composite helps: WHERE status = ? AND processed_at BETWEEN ? AND ?
            $table->index(['payment_schedule_id', 'status', 'processed_at'], 'pj_schedule_status_processed_idx');
        });

        // Payroll Jobs - Same pattern as payment jobs
        Schema::table('payroll_jobs', function (Blueprint $table) {
            // For time-series queries: WHERE status = 'succeeded' AND processed_at >= ?
            $table->index(['status', 'processed_at'], 'prj_status_processed_at_idx');

            // For filtering by processed_at alone
            $table->index(['processed_at'], 'prj_processed_at_idx');

            // For queries that filter status + aggregate by date
            $table->index(['payroll_schedule_id', 'status', 'processed_at'], 'prj_schedule_status_processed_idx');
        });

        // Payment Schedules - business_id composite for JOIN performance
        Schema::table('payment_schedules', function (Blueprint $table) {
            // For JOIN queries: WHERE business_id IN (?) with eager counts
            $table->index(['business_id', 'status'], 'ps_business_status_idx');

            // For upcoming schedules: WHERE business_id IN (?) AND status = 'active' AND next_run_at >= ?
            $table->index(['business_id', 'status', 'next_run_at'], 'ps_business_status_next_run_idx');
        });

        // Payroll Schedules - same pattern
        Schema::table('payroll_schedules', function (Blueprint $table) {
            // For JOIN queries
            $table->index(['business_id', 'status'], 'prs_business_status_idx');

            // For upcoming schedules queries
            $table->index(['business_id', 'status', 'next_run_at'], 'prs_business_status_next_run_idx');
        });

        // Recipients - for top recipients aggregation
        Schema::table('recipients', function (Blueprint $table) {
            $table->index(['business_id'], 'recipients_business_id_idx');
        });

        // Employees - for top employees aggregation
        Schema::table('employees', function (Blueprint $table) {
            // Check if index already exists before adding
            if (! $this->hasIndex('employees', 'employees_business_id_idx')) {
                $table->index(['business_id'], 'employees_business_id_idx');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_jobs', function (Blueprint $table) {
            $table->dropIndex('pj_status_processed_at_idx');
            $table->dropIndex('pj_processed_at_idx');
            $table->dropIndex('pj_schedule_status_processed_idx');
        });

        Schema::table('payroll_jobs', function (Blueprint $table) {
            $table->dropIndex('prj_status_processed_at_idx');
            $table->dropIndex('prj_processed_at_idx');
            $table->dropIndex('prj_schedule_status_processed_idx');
        });

        Schema::table('payment_schedules', function (Blueprint $table) {
            $table->dropIndex('ps_business_status_idx');
            $table->dropIndex('ps_business_status_next_run_idx');
        });

        Schema::table('payroll_schedules', function (Blueprint $table) {
            $table->dropIndex('prs_business_status_idx');
            $table->dropIndex('prs_business_status_next_run_idx');
        });

        Schema::table('recipients', function (Blueprint $table) {
            $table->dropIndex('recipients_business_id_idx');
        });

        Schema::table('employees', function (Blueprint $table) {
            if ($this->hasIndex('employees', 'employees_business_id_idx')) {
                $table->dropIndex('employees_business_id_idx');
            }
        });
    }

    /**
     * Check if an index exists on a table.
     */
    private function hasIndex(string $table, string $indexName): bool
    {
        $indexes = Schema::getIndexes($table);

        foreach ($indexes as $index) {
            if ($index['name'] === $indexName) {
                return true;
            }
        }

        return false;
    }
};
