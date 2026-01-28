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
        Schema::create('reconciliation_discrepancies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->onDelete('cascade');
            $table->string('discrepancy_type', 32); // stored_vs_calculated, stored_vs_ledger, etc.
            $table->decimal('stored_balance', 15, 2);
            $table->decimal('calculated_balance', 15, 2)->nullable();
            $table->decimal('ledger_balance', 15, 2)->nullable();
            $table->decimal('difference', 15, 2);
            $table->string('status', 16)->default('pending'); // pending, approved, compensated, resolved
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('approved_at')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'status']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reconciliation_discrepancies');
    }
};
