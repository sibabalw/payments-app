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
        Schema::table('adjustments', function (Blueprint $table) {
            $table->foreignId('payroll_schedule_id')
                ->nullable()
                ->after('employee_id')
                ->constrained()
                ->onDelete('cascade');

            $table->index('payroll_schedule_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('adjustments', function (Blueprint $table) {
            $table->dropForeign(['payroll_schedule_id']);
            $table->dropIndex(['payroll_schedule_id']);
            $table->dropColumn('payroll_schedule_id');
        });
    }
};
