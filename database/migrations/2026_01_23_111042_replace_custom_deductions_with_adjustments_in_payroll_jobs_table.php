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
            $table->renameColumn('custom_deductions', 'adjustments');
        });

        // Transform existing custom_deductions JSON structure to adjustments structure
        DB::table('payroll_jobs')
            ->whereNotNull('adjustments')
            ->get()
            ->each(function ($job) {
                $customDeductions = json_decode($job->adjustments, true) ?? [];

                if (empty($customDeductions)) {
                    return;
                }

                // Check if already in new format (has adjustment_type field)
                $firstItem = reset($customDeductions);
                if (is_array($firstItem) && isset($firstItem['adjustment_type'])) {
                    // Already in new format, skip
                    return;
                }

                // Transform custom_deductions structure to adjustments structure
                $adjustments = array_map(function ($deduction) {
                    return [
                        'name' => $deduction['name'] ?? 'Unknown',
                        'type' => $deduction['type'] ?? 'fixed',
                        'adjustment_type' => 'deduction',
                        'amount' => $deduction['amount'] ?? 0,
                        'original_amount' => $deduction['original_amount'] ?? $deduction['amount'] ?? 0,
                        'is_recurring' => true,
                    ];
                }, $customDeductions);

                DB::table('payroll_jobs')
                    ->where('id', $job->id)
                    ->update([
                        'adjustments' => json_encode($adjustments),
                    ]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payroll_jobs', function (Blueprint $table) {
            $table->renameColumn('adjustments', 'custom_deductions');
        });
    }
};
