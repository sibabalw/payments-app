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
        // If old payment_jobs table exists, drop foreign keys first, then drop the table
        if (Schema::hasTable('payment_jobs')) {
            // Drop foreign key constraints that reference payment_jobs
            Schema::table('billing_transactions', function (Blueprint $table) {
                $table->dropForeign(['payment_job_id']);
            });
            
            // Now drop the old table
            Schema::dropIfExists('payment_jobs');
        }
        
        // Rename payment_jobs_new to payment_jobs
        Schema::rename('payment_jobs_new', 'payment_jobs');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::rename('payment_jobs', 'payment_jobs_new');
    }
};
