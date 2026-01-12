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
        Schema::table('payment_jobs', function (Blueprint $table) {
            $table->string('transaction_id')->nullable()->after('processed_at');
            $table->json('gateway_metadata')->nullable()->after('transaction_id');
            
            $table->index('transaction_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_jobs', function (Blueprint $table) {
            $table->dropIndex(['transaction_id']);
            $table->dropColumn(['transaction_id', 'gateway_metadata']);
        });
    }
};
