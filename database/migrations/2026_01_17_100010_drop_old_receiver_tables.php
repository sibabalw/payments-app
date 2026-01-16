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
        // Drop old receiver tables (replaced by recipients)
        Schema::dropIfExists('payment_schedule_receiver');
        Schema::dropIfExists('receivers');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Note: We're not recreating these as they're replaced by recipients
    }
};
