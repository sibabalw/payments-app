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
        if (Schema::hasTable('financial_ledger') && ! Schema::hasColumn('financial_ledger', 'sequence_number')) {
            Schema::table('financial_ledger', function (Blueprint $table) {
                $table->unsignedBigInteger('sequence_number')->nullable()->after('id');
            });

            // Generate sequence numbers for existing entries (monotonic, gap-tolerant).
            // Use PHP loop so it works on MySQL, PostgreSQL, and SQLite (no driver-specific SQL).
            $entries = DB::table('financial_ledger')->orderBy('created_at')->orderBy('id')->get();
            $sequence = 1;
            foreach ($entries as $entry) {
                DB::table('financial_ledger')->where('id', $entry->id)->update(['sequence_number' => $sequence++]);
            }

            // Make it non-nullable after migration
            Schema::table('financial_ledger', function (Blueprint $table) {
                $table->unsignedBigInteger('sequence_number')->nullable(false)->change();
            });

            // Add index for fast replay queries
            Schema::table('financial_ledger', function (Blueprint $table) {
                $table->index(['account_type', 'sequence_number'], 'idx_account_sequence');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('financial_ledger', 'sequence_number')) {
            Schema::table('financial_ledger', function (Blueprint $table) {
                $table->dropIndex('idx_account_sequence');
                $table->dropColumn('sequence_number');
            });
        }
    }
};
