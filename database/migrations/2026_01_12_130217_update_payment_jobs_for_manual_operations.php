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
            // Check if columns exist before renaming
            if (Schema::hasColumn('payment_jobs', 'fee_released_at')) {
                $table->renameColumn('fee_released_at', 'fee_released_manually_at');
            } else {
                $table->timestamp('fee_released_manually_at')->nullable()->after('escrow_deposit_id');
            }

            if (Schema::hasColumn('payment_jobs', 'funds_returned_at')) {
                $table->renameColumn('funds_returned_at', 'funds_returned_manually_at');
            } else {
                $table->timestamp('funds_returned_manually_at')->nullable()->after('fee_released_manually_at');
            }
            
            // Add who recorded the operation
            if (!Schema::hasColumn('payment_jobs', 'released_by')) {
                $table->foreignId('released_by')->nullable()->after('funds_returned_manually_at')->constrained('users')->onDelete('set null');
                $table->index('released_by');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_jobs', function (Blueprint $table) {
            if (Schema::hasColumn('payment_jobs', 'released_by')) {
                $table->dropIndex(['released_by']);
                $table->dropForeign(['released_by']);
                $table->dropColumn('released_by');
            }
            
            if (Schema::hasColumn('payment_jobs', 'fee_released_manually_at')) {
                $table->renameColumn('fee_released_manually_at', 'fee_released_at');
            }
            
            if (Schema::hasColumn('payment_jobs', 'funds_returned_manually_at')) {
                $table->renameColumn('funds_returned_manually_at', 'funds_returned_at');
            }
        });
    }
};
