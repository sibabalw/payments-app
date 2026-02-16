<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds CHECK constraints to ensure escrow_balance and hold_amount are non-negative.
     * This is a critical constraint to prevent invalid financial states.
     */
    public function up(): void
    {
        $dbDriver = DB::getDriverName();

        if ($dbDriver === 'pgsql') {
            // Helper function to add constraint only if it doesn't exist
            $addConstraintIfNotExists = function ($table, $constraintName, $checkClause) {
                DB::unprepared("
                    DO \$\$
                    BEGIN
                        IF NOT EXISTS (
                            SELECT 1 FROM pg_constraint 
                            WHERE conname = '{$constraintName}' 
                            AND conrelid = '{$table}'::regclass
                        ) THEN
                            ALTER TABLE {$table} 
                            ADD CONSTRAINT {$constraintName} 
                            CHECK ({$checkClause});
                        END IF;
                    END \$\$;
                ");
            };

            // Ensure escrow_balance is never negative (critical financial constraint)
            $addConstraintIfNotExists('businesses', 'chk_businesses_escrow_balance_non_negative', 'escrow_balance IS NULL OR escrow_balance >= 0');

            // Ensure hold_amount is never negative
            $addConstraintIfNotExists('businesses', 'chk_businesses_hold_amount_non_negative', 'hold_amount IS NULL OR hold_amount >= 0');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $dbDriver = DB::getDriverName();

        if ($dbDriver === 'pgsql') {
            DB::statement('ALTER TABLE businesses DROP CONSTRAINT IF EXISTS chk_businesses_escrow_balance_non_negative');
            DB::statement('ALTER TABLE businesses DROP CONSTRAINT IF EXISTS chk_businesses_hold_amount_non_negative');
        }
    }
};
