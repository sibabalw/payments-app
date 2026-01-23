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
        // Drop custom_deductions table after data has been migrated to adjustments
        Schema::dropIfExists('custom_deductions');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Recreate the custom_deductions table structure
        // Note: This does not restore data - data would need to be restored from backups
        Schema::create('custom_deductions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->onDelete('cascade');
            $table->foreignId('employee_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('name');
            $table->enum('type', ['fixed', 'percentage'])->default('fixed');
            $table->decimal('amount', 15, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'employee_id']);
            $table->index(['business_id', 'is_active']);
        });
    }
};
