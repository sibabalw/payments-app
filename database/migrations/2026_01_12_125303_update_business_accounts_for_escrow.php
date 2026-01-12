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
        Schema::table('business_accounts', function (Blueprint $table) {
            if (!Schema::hasColumn('business_accounts', 'business_id')) {
                $table->foreignId('business_id')->unique()->after('id');
            }
            if (!Schema::hasColumn('business_accounts', 'escrow_account_number')) {
                $table->string('escrow_account_number')->nullable()->after('business_id');
            }
            if (!Schema::hasColumn('business_accounts', 'current_balance')) {
                $table->decimal('current_balance', 15, 2)->default(0)->after('escrow_account_number');
            }
            if (!Schema::hasColumn('business_accounts', 'total_deposited')) {
                $table->decimal('total_deposited', 15, 2)->default(0)->after('current_balance');
            }
            if (!Schema::hasColumn('business_accounts', 'total_deposit_fees_charged')) {
                $table->decimal('total_deposit_fees_charged', 15, 2)->default(0)->after('total_deposited');
            }
            if (!Schema::hasColumn('business_accounts', 'currency')) {
                $table->string('currency', 3)->default('ZAR')->after('total_deposit_fees_charged');
            }
            if (!Schema::hasColumn('business_accounts', 'last_deposit_at')) {
                $table->timestamp('last_deposit_at')->nullable()->after('currency');
            }
            if (!Schema::hasColumn('business_accounts', 'last_balance_update_at')) {
                $table->timestamp('last_balance_update_at')->nullable()->after('last_deposit_at');
            }
        });

        // Add foreign key and index separately with error handling
        if (Schema::hasColumn('business_accounts', 'business_id')) {
            try {
                Schema::table('business_accounts', function (Blueprint $table) {
                    $table->foreign('business_id')->references('id')->on('businesses')->onDelete('cascade');
                });
            } catch (\Exception $e) {
                // Foreign key might already exist, ignore
            }

            try {
                Schema::table('business_accounts', function (Blueprint $table) {
                    $table->index('business_id');
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
        Schema::table('business_accounts', function (Blueprint $table) {
            $table->dropForeign(['business_id']);
            $table->dropIndex(['business_id']);
            $table->dropColumn([
                'business_id',
                'escrow_account_number',
                'current_balance',
                'total_deposited',
                'total_deposit_fees_charged',
                'currency',
                'last_deposit_at',
                'last_balance_update_at',
            ]);
        });
    }
};
