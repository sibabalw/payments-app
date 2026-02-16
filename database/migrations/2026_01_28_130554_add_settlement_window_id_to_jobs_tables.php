<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add settlement_window_id to payroll_jobs
        if (Schema::hasTable('payroll_jobs') && ! Schema::hasColumn('payroll_jobs', 'settlement_window_id')) {
            Schema::table('payroll_jobs', function (Blueprint $table) {
                $table->foreignId('settlement_window_id')->nullable()->after('employee_snapshot')
                    ->constrained('settlement_windows')->onDelete('set null');
            });
        }

        // Add settlement_window_id to payment_jobs
        if (Schema::hasTable('payment_jobs') && ! Schema::hasColumn('payment_jobs', 'settlement_window_id')) {
            Schema::table('payment_jobs', function (Blueprint $table) {
                $table->foreignId('settlement_window_id')->nullable()->after('released_by')
                    ->constrained('settlement_windows')->onDelete('set null');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('payroll_jobs', 'settlement_window_id')) {
            Schema::table('payroll_jobs', function (Blueprint $table) {
                $table->dropForeign(['settlement_window_id']);
                $table->dropColumn('settlement_window_id');
            });
        }

        if (Schema::hasColumn('payment_jobs', 'settlement_window_id')) {
            Schema::table('payment_jobs', function (Blueprint $table) {
                $table->dropForeign(['settlement_window_id']);
                $table->dropColumn('settlement_window_id');
            });
        }
    }
};
