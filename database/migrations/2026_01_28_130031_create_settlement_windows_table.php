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
        Schema::create('settlement_windows', function (Blueprint $table) {
            $table->id();
            $table->string('window_type', 32); // hourly, daily, custom
            $table->timestamp('window_start');
            $table->timestamp('window_end');
            $table->string('status', 16)->default('pending'); // pending, processing, settled, failed
            $table->integer('transaction_count')->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->json('metadata')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->timestamps();

            $table->index(['window_type', 'status', 'window_start']);
            $table->index(['window_start', 'window_end']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settlement_windows');
    }
};
