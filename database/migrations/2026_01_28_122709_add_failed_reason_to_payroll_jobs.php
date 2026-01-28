<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds failed_reason and permanently_failed_at fields for dead letter queue pattern.
     */
    public function up(): void
    {
        Schema::table('payroll_jobs', function (Blueprint $table) {
            // Add field to track permanently failed jobs (dead letter queue)
            $table->timestamp('permanently_failed_at')->nullable()->after('processed_at');
            $table->string('failed_reason', 50)->nullable()->after('permanently_failed_at');
        });

        // Mark existing failed jobs older than 24 hours as permanently failed
        \DB::table('payroll_jobs')
            ->where('status', 'failed')
            ->where('processed_at', '<', now()->subDay())
            ->update([
                'permanently_failed_at' => \DB::raw('processed_at'),
                'failed_reason' => 'auto_marked',
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payroll_jobs', function (Blueprint $table) {
            $table->dropColumn(['failed_reason', 'permanently_failed_at']);
        });
    }
};
