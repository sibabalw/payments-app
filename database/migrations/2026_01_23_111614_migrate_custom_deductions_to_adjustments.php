<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Check if custom_deductions table exists
        if (! Schema::hasTable('custom_deductions')) {
            return;
        }

        // Migrate all CustomDeduction records to Adjustment records
        $customDeductions = DB::table('custom_deductions')->get();

        foreach ($customDeductions as $deduction) {
            DB::table('adjustments')->insert([
                'business_id' => $deduction->business_id,
                'employee_id' => $deduction->employee_id,
                'name' => $deduction->name,
                'type' => $deduction->type,
                'amount' => $deduction->amount,
                'adjustment_type' => 'deduction', // All migrated records are deductions
                'is_recurring' => true, // Old deductions were always recurring
                'payroll_period_start' => null, // Recurring adjustments have null period
                'payroll_period_end' => null,
                'is_active' => $deduction->is_active,
                'description' => $deduction->description,
                'created_at' => $deduction->created_at,
                'updated_at' => $deduction->updated_at,
            ]);
        }

        // Note: The column rename migration will handle renaming custom_deductions to adjustments
        // and the JSON structure will be updated by that migration if needed
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This migration is not easily reversible
        // Data would need to be manually restored from backups
    }
};
