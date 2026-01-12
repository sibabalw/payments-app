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
            // Remove escrow-specific fields since we use a single platform account
            // Keep the table structure but remove escrow tracking
            if (Schema::hasColumn('business_accounts', 'escrow_account_number')) {
                $table->dropColumn('escrow_account_number');
            }
            if (Schema::hasColumn('business_accounts', 'current_balance')) {
                $table->dropColumn('current_balance');
            }
            if (Schema::hasColumn('business_accounts', 'total_deposited')) {
                $table->dropColumn('total_deposited');
            }
            if (Schema::hasColumn('business_accounts', 'total_deposit_fees_charged')) {
                $table->dropColumn('total_deposit_fees_charged');
            }
            if (Schema::hasColumn('business_accounts', 'currency')) {
                $table->dropColumn('currency');
            }
            if (Schema::hasColumn('business_accounts', 'last_deposit_at')) {
                $table->dropColumn('last_deposit_at');
            }
            if (Schema::hasColumn('business_accounts', 'last_balance_update_at')) {
                $table->dropColumn('last_balance_update_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('business_accounts', function (Blueprint $table) {
            $table->string('escrow_account_number')->nullable();
            $table->decimal('current_balance', 15, 2)->default(0);
            $table->decimal('total_deposited', 15, 2)->default(0);
            $table->decimal('total_deposit_fees_charged', 15, 2)->default(0);
            $table->string('currency', 3)->default('ZAR');
            $table->timestamp('last_deposit_at')->nullable();
            $table->timestamp('last_balance_update_at')->nullable();
        });
    }
};
