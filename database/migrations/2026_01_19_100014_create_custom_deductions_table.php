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
        Schema::create('custom_deductions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->onDelete('cascade');
            $table->foreignId('employee_id')->nullable()->constrained()->onDelete('cascade');
            // If employee_id is null, it's a company-wide deduction
            // If employee_id is set, it's specific to that employee
            $table->string('name'); // e.g., "Medical Aid", "Pension Fund"
            $table->enum('type', ['fixed', 'percentage'])->default('fixed');
            // fixed: fixed amount per month
            // percentage: percentage of gross salary
            $table->decimal('amount', 15, 2)->default(0); // Fixed amount or percentage value
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'employee_id']);
            $table->index(['business_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('custom_deductions');
    }
};
