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
        Schema::create('adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->onDelete('cascade');
            $table->foreignId('employee_id')->nullable()->constrained()->onDelete('cascade');
            // If employee_id is null, it's a company-wide adjustment
            // If employee_id is set, it's specific to that employee
            $table->string('name'); // e.g., "Medical Aid", "Pension Fund", "Bonus"
            $table->enum('type', ['fixed', 'percentage'])->default('fixed');
            // fixed: fixed amount per month
            // percentage: percentage of gross salary
            $table->decimal('amount', 15, 2)->default(0); // Fixed amount or percentage value
            $table->enum('adjustment_type', ['deduction', 'addition'])->default('deduction');
            // deduction: reduces net salary
            // addition: increases net salary
            $table->boolean('is_recurring')->default(true);
            // true: applied automatically on every payroll run until removed
            // false: scoped to a single payroll period only
            $table->date('payroll_period_start')->nullable();
            // Required for once-off adjustments (is_recurring = false)
            // Null for recurring adjustments (is_recurring = true)
            $table->date('payroll_period_end')->nullable();
            // Required for once-off adjustments (is_recurring = false)
            // Null for recurring adjustments (is_recurring = true)
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'employee_id']);
            $table->index(['business_id', 'is_active']);
            $table->index(['business_id', 'is_recurring']);
            $table->index(['payroll_period_start', 'payroll_period_end']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('adjustments');
    }
};
