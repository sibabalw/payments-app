<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add schedule_run_id to payroll_jobs for audit trail
        if (Schema::hasTable('payroll_jobs') && ! Schema::hasColumn('payroll_jobs', 'schedule_run_id')) {
            Schema::table('payroll_jobs', function (Blueprint $table) {
                $table->string('schedule_run_id', 64)->nullable()->after('payroll_schedule_id');
                $table->index('schedule_run_id', 'idx_payroll_jobs_schedule_run_id');
            });
        }

        // Add schedule_run_id to payment_jobs for audit trail
        if (Schema::hasTable('payment_jobs') && ! Schema::hasColumn('payment_jobs', 'schedule_run_id')) {
            Schema::table('payment_jobs', function (Blueprint $table) {
                $table->string('schedule_run_id', 64)->nullable()->after('payment_schedule_id');
                $table->index('schedule_run_id', 'idx_payment_jobs_schedule_run_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('payroll_jobs') && Schema::hasColumn('payroll_jobs', 'schedule_run_id')) {
            Schema::table('payroll_jobs', function (Blueprint $table) {
                $table->dropIndex('idx_payroll_jobs_schedule_run_id');
                $table->dropColumn('schedule_run_id');
            });
        }

        if (Schema::hasTable('payment_jobs') && Schema::hasColumn('payment_jobs', 'schedule_run_id')) {
            Schema::table('payment_jobs', function (Blueprint $table) {
                $table->dropIndex('idx_payment_jobs_schedule_run_id');
                $table->dropColumn('schedule_run_id');
            });
        }
    }
};
