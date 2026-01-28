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
        if (Schema::hasTable('financial_ledger') && ! Schema::hasColumn('financial_ledger', 'posting_state')) {
            Schema::table('financial_ledger', function (Blueprint $table) {
                $table->string('posting_state', 16)->default('PENDING')->after('effective_at');
                $table->timestamp('posted_at')->nullable()->after('posting_state');
            });

            // Set all existing entries to POSTED (they're already processed)
            DB::table('financial_ledger')->update([
                'posting_state' => 'POSTED',
                'posted_at' => DB::raw('created_at'),
            ]);

            // Add index for posting state queries
            Schema::table('financial_ledger', function (Blueprint $table) {
                $table->index(['posting_state', 'effective_at']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('financial_ledger', 'posting_state')) {
            Schema::table('financial_ledger', function (Blueprint $table) {
                $table->dropIndex(['posting_state', 'effective_at']);
                $table->dropColumn(['posting_state', 'posted_at']);
            });
        }
    }
};
