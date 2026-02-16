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
        Schema::create('payment_jobs_new', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_schedule_id')->constrained('payment_schedules')->onDelete('cascade');
            $table->foreignId('recipient_id')->constrained()->onDelete('cascade');
            $table->decimal('amount', 15, 2);
            $table->string('currency', 3)->default('ZAR');
            $table->enum('status', ['pending', 'processing', 'succeeded', 'failed'])->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->string('transaction_id')->nullable();
            $table->decimal('fee', 15, 2)->nullable();
            $table->foreignId('escrow_deposit_id')->nullable()->constrained()->onDelete('set null');
            $table->timestamp('fee_released_manually_at')->nullable();
            $table->timestamp('funds_returned_manually_at')->nullable();
            $table->foreignId('released_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            $table->index(['payment_schedule_id', 'status']);
            $table->index(['recipient_id', 'status']);
            $table->index('escrow_deposit_id');
            $table->index('released_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_jobs_new');
    }
};
