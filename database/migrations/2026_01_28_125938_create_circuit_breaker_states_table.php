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
        Schema::create('circuit_breaker_states', function (Blueprint $table) {
            $table->string('key', 128)->primary();
            $table->string('state', 16)->default('closed');
            $table->integer('failure_count')->default(0);
            $table->integer('success_count')->default(0);
            $table->timestamp('last_failure_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamps();

            $table->index('state');
            $table->index('last_failure_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('circuit_breaker_states');
    }
};
