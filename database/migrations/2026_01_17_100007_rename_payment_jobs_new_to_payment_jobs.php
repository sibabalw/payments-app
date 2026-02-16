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
        // If old payment_jobs table exists, drop foreign keys first (if any), then drop the table
        if (Schema::hasTable('payment_jobs')) {
            // Only drop foreign key if billing_transactions has the column and the constraint exists
            if (Schema::hasTable('billing_transactions') && Schema::hasColumn('billing_transactions', 'payment_job_id')) {
                try {
                    Schema::table('billing_transactions', function (Blueprint $table) {
                        $table->dropForeign(['payment_job_id']);
                    });
                } catch (\Illuminate\Database\QueryException $e) {
                    // MySQL 1091: constraint doesn't exist; ignore so migration can continue
                    if (str_contains($e->getMessage(), '1091') === false) {
                        throw $e;
                    }
                }
            }

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
