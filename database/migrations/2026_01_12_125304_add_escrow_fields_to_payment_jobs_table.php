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
            if (!Schema::hasColumn('payment_jobs', 'fee')) {
                $table->decimal('fee', 15, 2)->nullable()->after('amount');
            }
            if (!Schema::hasColumn('payment_jobs', 'escrow_deposit_id')) {
                $table->foreignId('escrow_deposit_id')->nullable()->after('fee');
            }
            if (!Schema::hasColumn('payment_jobs', 'fee_released_at')) {
                $table->timestamp('fee_released_at')->nullable()->after('escrow_deposit_id');
            }
            if (!Schema::hasColumn('payment_jobs', 'funds_returned_at')) {
                $table->timestamp('funds_returned_at')->nullable()->after('fee_released_at');
            }
        });

        // Add foreign key and index separately with error handling
        if (Schema::hasColumn('payment_jobs', 'escrow_deposit_id')) {
            try {
                Schema::table('payment_jobs', function (Blueprint $table) {
                    $table->foreign('escrow_deposit_id')->references('id')->on('escrow_deposits')->onDelete('set null');
                });
            } catch (\Exception $e) {
                // Foreign key might already exist, ignore
            }

            try {
                Schema::table('payment_jobs', function (Blueprint $table) {
                    $table->index('escrow_deposit_id');
                });
            } catch (\Exception $e) {
                // Index might already exist, ignore
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_jobs', function (Blueprint $table) {
            $table->dropForeign(['escrow_deposit_id']);
            $table->dropIndex(['escrow_deposit_id']);
            $table->dropColumn(['fee', 'escrow_deposit_id', 'fee_released_at', 'funds_returned_at']);
        });
    }
};
