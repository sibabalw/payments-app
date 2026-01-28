<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Creates a high-performance sequence table for ledger sequence numbers.
     * Uses atomic operations to eliminate MAX() query bottlenecks.
     */
    public function up(): void
    {
        Schema::create('ledger_sequence', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('value')->default(0);
            $table->timestamps();
        });

        // Insert initial row with value 0
        DB::table('ledger_sequence')->insert([
            'value' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ledger_sequence');
    }
};
