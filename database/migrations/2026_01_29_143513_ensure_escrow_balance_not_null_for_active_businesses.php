<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds CHECK constraint to ensure escrow_balance is NOT NULL for active businesses.
     * This is a critical constraint to prevent creating payment/payroll jobs for businesses
     * with NULL escrow balance, which would bypass balance validation checks.
     */
    public function up(): void
    {
        $dbDriver = DB::getDriverName();

        if ($dbDriver === 'pgsql') {
            // Ensure escrow_balance is NOT NULL for active businesses
            // This prevents creating payment/payroll jobs when balance is NULL
            DB::unprepared("
                DO \$\$
                BEGIN
                    IF NOT EXISTS (
                        SELECT 1 FROM pg_constraint 
                        WHERE conname = 'chk_businesses_escrow_balance_not_null_when_active' 
                        AND conrelid = 'businesses'::regclass
                    ) THEN
                        ALTER TABLE businesses 
                        ADD CONSTRAINT chk_businesses_escrow_balance_not_null_when_active 
                        CHECK (
                            status != 'active' OR 
                            escrow_balance IS NOT NULL
                        );
                    END IF;
                END \$\$;
            ");
        } elseif ($dbDriver === 'mysql') {
            // MySQL 8.0.16+ supports CHECK constraints
            try {
                DB::statement('
                    ALTER TABLE businesses 
                    ADD CONSTRAINT chk_businesses_escrow_balance_not_null_when_active 
                    CHECK (
                        status != \'active\' OR 
                        escrow_balance IS NOT NULL
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
            DB::statement('ALTER TABLE businesses DROP CONSTRAINT IF EXISTS chk_businesses_escrow_balance_not_null_when_active');
        } elseif ($dbDriver === 'mysql') {
            try {
                DB::statement('ALTER TABLE businesses DROP CHECK chk_businesses_escrow_balance_not_null_when_active');
            } catch (\Exception $e) {
                // Ignore if constraint doesn't exist
            }
        }
    }
};
