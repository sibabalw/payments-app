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
        // Add version column to payroll_jobs
        if (Schema::hasTable('payroll_jobs') && ! Schema::hasColumn('payroll_jobs', 'version')) {
            Schema::table('payroll_jobs', function (Blueprint $table) {
                $table->unsignedInteger('version')->default(1)->after('employee_snapshot');
            });
        }

        // Add version column to payment_jobs
        if (Schema::hasTable('payment_jobs') && ! Schema::hasColumn('payment_jobs', 'version')) {
            Schema::table('payment_jobs', function (Blueprint $table) {
                $table->unsignedInteger('version')->default(1)->after('released_by');
            });
        }

        // Add version column to businesses
        if (Schema::hasTable('businesses') && ! Schema::hasColumn('businesses', 'version')) {
            Schema::table('businesses', function (Blueprint $table) {
                $table->unsignedInteger('version')->default(1)->after('escrow_balance');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('payroll_jobs', 'version')) {
            Schema::table('payroll_jobs', function (Blueprint $table) {
                $table->dropColumn('version');
            });
        }

        if (Schema::hasColumn('payment_jobs', 'version')) {
            Schema::table('payment_jobs', function (Blueprint $table) {
                $table->dropColumn('version');
            });
        }

        if (Schema::hasColumn('businesses', 'version')) {
            Schema::table('businesses', function (Blueprint $table) {
                $table->dropColumn('version');
            });
        }
    }
};
