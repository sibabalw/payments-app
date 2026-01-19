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
            $table->decimal('hourly_rate', 10, 2)->nullable()->after('gross_salary');
            $table->decimal('overtime_rate_multiplier', 4, 2)->default(1.5)->after('hourly_rate');
            $table->decimal('weekend_rate_multiplier', 4, 2)->default(1.5)->after('overtime_rate_multiplier');
            $table->decimal('holiday_rate_multiplier', 4, 2)->default(2.0)->after('weekend_rate_multiplier');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn([
                'hourly_rate',
                'overtime_rate_multiplier',
                'weekend_rate_multiplier',
                'holiday_rate_multiplier',
            ]);
        });
    }
};
