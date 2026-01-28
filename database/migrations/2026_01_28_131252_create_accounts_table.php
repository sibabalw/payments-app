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
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique(); // e.g., "ESCROW_001", "TAX_PAYE"
            $table->string('name');
            $table->enum('type', ['ASSET', 'LIABILITY', 'EQUITY', 'REVENUE', 'EXPENSE']);
            $table->string('category', 32); // ESCROW, TAX, FEES, PAYROLL, etc.
            $table->string('owner_type', 128)->nullable(); // Business, System
            $table->unsignedBigInteger('owner_id')->nullable();
            $table->string('currency', 3)->default('ZAR');
            $table->boolean('is_system_account')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['owner_type', 'owner_id']);
            $table->index(['category', 'is_active']);
            $table->index('currency');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
