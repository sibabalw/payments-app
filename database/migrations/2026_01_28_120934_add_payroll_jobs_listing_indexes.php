<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds composite index for job listing queries that filter by schedule, status, and order by created_at.
     */
    public function up(): void
    {
        Schema::table('payroll_jobs', function (Blueprint $table) {
            // Index for job listing queries: WHERE payroll_schedule_id = ? AND status = ? ORDER BY created_at DESC
            // This optimizes the jobs() method in PayrollController
            if (! $this->hasIndex('payroll_jobs', 'payroll_jobs_schedule_status_created_idx')) {
                $table->index(['payroll_schedule_id', 'status', 'created_at'], 'payroll_jobs_schedule_status_created_idx');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payroll_jobs', function (Blueprint $table) {
            if ($this->hasIndex('payroll_jobs', 'payroll_jobs_schedule_status_created_idx')) {
                $table->dropIndex('payroll_jobs_schedule_status_created_idx');
            }
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
