<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('financial_ledger') && ! Schema::hasColumn('financial_ledger', 'amount_minor_units')) {
            Schema::table('financial_ledger', function (Blueprint $table) {
                $table->bigInteger('amount_minor_units')->nullable()->after('amount');
            });

            // Migrate existing data: convert decimal amounts to minor units (cents for ZAR)
            // amount_minor_units = amount * 100
            DB::statement('UPDATE financial_ledger SET amount_minor_units = ROUND(amount * 100) WHERE amount_minor_units IS NULL');

            // Make it non-nullable after migration
            Schema::table('financial_ledger', function (Blueprint $table) {
                $table->bigInteger('amount_minor_units')->nullable(false)->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('financial_ledger', 'amount_minor_units')) {
            Schema::table('financial_ledger', function (Blueprint $table) {
                $table->dropColumn('amount_minor_units');
            });
        }
    }
};
