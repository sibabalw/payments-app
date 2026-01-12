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
        Schema::create('payment_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_schedule_id')->constrained()->onDelete('cascade');
            $table->foreignId('receiver_id')->constrained()->onDelete('cascade');
            $table->decimal('amount', 15, 2);
            $table->string('currency', 3)->default('USD');
            $table->enum('status', ['pending', 'processing', 'succeeded', 'failed'])->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['payment_schedule_id', 'status']);
            $table->index(['receiver_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_jobs');
    }
};
