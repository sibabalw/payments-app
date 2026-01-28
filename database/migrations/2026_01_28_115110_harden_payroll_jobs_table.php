<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('payroll_jobs', function (Blueprint $table) {
            // First, add new columns for calculation snapshot (nullable initially)
            $table->string('calculation_hash', 64)->nullable()->after('pay_period_end');
            $table->json('calculation_snapshot')->nullable()->after('calculation_hash');
            $table->json('employee_snapshot')->nullable()->after('calculation_snapshot');
        });

        // Backfill period dates for any existing null values (use created_at date as fallback)
        DB::table('payroll_jobs')
            ->whereNull('pay_period_start')
            ->orWhereNull('pay_period_end')
            ->update([
                'pay_period_start' => DB::raw('DATE(created_at)'),
                'pay_period_end' => DB::raw('DATE(created_at)'),
            ]);

        Schema::table('payroll_jobs', function (Blueprint $table) {
            // Make period dates NOT NULL
            $table->date('pay_period_start')->nullable(false)->change();
            $table->date('pay_period_end')->nullable(false)->change();

            // Add unique constraint to prevent duplicate payroll jobs for same employee+period
            // Note: MySQL doesn't support partial unique indexes, so we'll enforce this at application level
            // But we still add the unique index for all statuses to catch duplicates early
            $table->unique(['employee_id', 'pay_period_start', 'pay_period_end'], 'payroll_jobs_employee_period_unique');

            // Add performance indexes for critical queries
            $table->index(['employee_id', 'pay_period_start', 'pay_period_end', 'status'], 'payroll_jobs_employee_period_status_idx');
            $table->index(['payroll_schedule_id', 'pay_period_start', 'pay_period_end'], 'payroll_jobs_schedule_period_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payroll_jobs', function (Blueprint $table) {
            // Drop indexes first
            $table->dropIndex('payroll_jobs_schedule_period_idx');
            $table->dropIndex('payroll_jobs_employee_period_status_idx');
            $table->dropUnique('payroll_jobs_employee_period_unique');

            // Revert period dates to nullable
            $table->date('pay_period_start')->nullable()->change();
            $table->date('pay_period_end')->nullable()->change();

            // Drop new columns
            $table->dropColumn(['employee_snapshot', 'calculation_snapshot', 'calculation_hash']);
        });
    }
};
