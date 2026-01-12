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
        Schema::create('escrow_deposits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->onDelete('cascade');
            $table->decimal('amount', 15, 2);
            $table->decimal('fee_amount', 15, 2);
            $table->decimal('authorized_amount', 15, 2);
            $table->string('currency', 3)->default('ZAR');
            $table->enum('status', ['pending', 'completed', 'failed'])->default('pending');
            $table->string('bank_reference')->nullable();
            $table->timestamp('deposited_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'status']);
            $table->index('deposited_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('escrow_deposits');
    }
};
