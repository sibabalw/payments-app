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
        Schema::table('employees', function (Blueprint $table) {
            $table->decimal('hours_worked_per_month', 5, 2)->nullable()->after('employment_type');
            // Default to null - if null, assume full-time (exempt from UIF exemption)
            // If set and < 24, employee is exempt from UIF
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('hours_worked_per_month');
        });
    }
};
