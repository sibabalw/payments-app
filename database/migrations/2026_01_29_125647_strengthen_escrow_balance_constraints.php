<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds additional CHECK constraints to strengthen escrow balance validation.
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

            // Ensure hold_amount doesn't exceed escrow_balance
            // This prevents invalid state where hold_amount > escrow_balance
            $addConstraintIfNotExists('businesses', 'chk_businesses_hold_amount_not_exceed_balance', 'hold_amount IS NULL OR escrow_balance IS NULL OR hold_amount <= escrow_balance');

            // Ensure available balance calculation is always valid
            // This constraint ensures escrow_balance - hold_amount >= 0 (when both are not null)
            $addConstraintIfNotExists('businesses', 'chk_businesses_available_balance_non_negative', 'escrow_balance IS NULL OR hold_amount IS NULL OR (escrow_balance - hold_amount) >= 0');
        } elseif ($dbDriver === 'mysql') {
            // MySQL 8.0.16+ supports CHECK constraints
            try {
                DB::statement('
                    ALTER TABLE businesses 
                    ADD CONSTRAINT chk_businesses_hold_amount_not_exceed_balance 
                    CHECK (hold_amount IS NULL OR escrow_balance IS NULL OR hold_amount <= escrow_balance)
                ');

                DB::statement('
                    ALTER TABLE businesses 
                    ADD CONSTRAINT chk_businesses_available_balance_non_negative 
                    CHECK (
                        escrow_balance IS NULL OR 
                        hold_amount IS NULL OR 
                        (escrow_balance - hold_amount) >= 0
                    )
                ');
            } catch (\Exception $e) {
                // Older MySQL versions don't support CHECK constraints
                // Rely on application-level validation
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $dbDriver = DB::getDriverName();

        if ($dbDriver === 'pgsql') {
            DB::statement('ALTER TABLE businesses DROP CONSTRAINT IF EXISTS chk_businesses_hold_amount_not_exceed_balance');
            DB::statement('ALTER TABLE businesses DROP CONSTRAINT IF EXISTS chk_businesses_available_balance_non_negative');
        } elseif ($dbDriver === 'mysql') {
            try {
                DB::statement('ALTER TABLE businesses DROP CHECK chk_businesses_hold_amount_not_exceed_balance');
                DB::statement('ALTER TABLE businesses DROP CHECK chk_businesses_available_balance_non_negative');
            } catch (\Exception $e) {
                // Ignore if constraints don't exist
            }
        }
    }
};
