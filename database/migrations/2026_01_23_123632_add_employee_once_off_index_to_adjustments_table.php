<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds composite index for fast lookups of employee-specific once-off adjustments.
     * This index is critical for the exact matching logic used when processing payroll
     * to ensure each schedule only picks up adjustments tied to it.
     */
    public function up(): void
    {
        Schema::table('adjustments', function (Blueprint $table) {
            // Composite index for employee-specific once-off adjustment lookups
            // Used when processing payroll to match: employee_id + payroll_schedule_id + exact period
            $table->index(
                ['employee_id', 'payroll_schedule_id', 'payroll_period_start', 'payroll_period_end', 'is_recurring'],
                'idx_employee_once_off_adjustment'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('adjustments', function (Blueprint $table) {
            $table->dropIndex('idx_employee_once_off_adjustment');
        });
    }
};
