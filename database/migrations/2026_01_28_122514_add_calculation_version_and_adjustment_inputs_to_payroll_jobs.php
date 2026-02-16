<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds calculation_version and adjustment_inputs columns to track calculation logic changes
     * and store original adjustment calculation inputs separately from outputs.
     */
    public function up(): void
    {
        Schema::table('payroll_jobs', function (Blueprint $table) {
            // Add calculation version to track when calculation logic changes
            $table->unsignedInteger('calculation_version')->default(1)->after('calculation_hash');

            // Store original adjustment calculation inputs (before calculation)
            // This allows verification that stored adjustments match original inputs
            $table->json('adjustment_inputs')->nullable()->after('calculation_version');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payroll_jobs', function (Blueprint $table) {
            $table->dropColumn(['adjustment_inputs', 'calculation_version']);
        });
    }
};
