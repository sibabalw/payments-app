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
        Schema::create('payment_schedule_receiver', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_schedule_id')->constrained()->onDelete('cascade');
            $table->foreignId('receiver_id')->constrained()->onDelete('cascade');
            $table->timestamps();

            $table->unique(['payment_schedule_id', 'receiver_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_schedule_receiver');
    }
};
