<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds CHECK constraint to ensure escrow_balance is greater than zero for active businesses.
     * This complements the existing NOT NULL constraint and provides an additional database-level safeguard
     * to prevent active businesses from having zero or negative escrow balance.
     */
    public function up(): void
    {
        $dbDriver = DB::getDriverName();

        if ($dbDriver === 'pgsql') {
            // First, fix any existing data that violates the constraint
            // Active businesses with escrow_balance <= 0 need to be fixed
            // Option 1: Set escrow_balance to a minimal positive value (0.01) for active businesses
            // Option 2: Set status to 'inactive' if balance is zero/negative
            // We'll use Option 1 to preserve business status, but log the fix
            DB::unprepared("
                DO \$\$
                DECLARE
                    fixed_count INTEGER;
                BEGIN
                    -- Fix active businesses with zero or negative escrow_balance
                    -- Set to minimal positive value (0.01) to satisfy constraint
                    UPDATE businesses 
                    SET escrow_balance = 0.01
                    WHERE status = 'active' 
                      AND escrow_balance IS NOT NULL 
                      AND escrow_balance <= 0;
                    
                    GET DIAGNOSTICS fixed_count = ROW_COUNT;
                    
                    IF fixed_count > 0 THEN
                        RAISE NOTICE 'Fixed % active businesses with zero/negative escrow_balance by setting to 0.01', fixed_count;
                    END IF;
                END \$\$;
            ");

            // Now add the constraint
            // Ensure escrow_balance is greater than zero for active businesses
            // This prevents active businesses from having zero balance, which would allow job creation attempts
            // Note: This is a strict constraint - if a business needs to have zero balance temporarily,
            // they should be set to inactive status first
            DB::unprepared("
                DO \$\$
                BEGIN
                    IF NOT EXISTS (
                        SELECT 1 FROM pg_constraint 
                        WHERE conname = 'chk_businesses_escrow_balance_positive_when_active' 
                        AND conrelid = 'businesses'::regclass
                    ) THEN
                        ALTER TABLE businesses 
                        ADD CONSTRAINT chk_businesses_escrow_balance_positive_when_active 
                        CHECK (
                            status != 'active' OR 
                            escrow_balance IS NULL OR 
                            escrow_balance > 0
                        );
                    END IF;
                END \$\$;
            ");
        } elseif ($dbDriver === 'mysql') {
            // MySQL 8.0.16+ supports CHECK constraints
            try {
                DB::statement('
                    ALTER TABLE businesses 
                    ADD CONSTRAINT chk_businesses_escrow_balance_positive_when_active 
                    CHECK (
                        status != \'active\' OR 
                        escrow_balance IS NULL OR 
                        escrow_balance > 0
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
            DB::statement('ALTER TABLE businesses DROP CONSTRAINT IF EXISTS chk_businesses_escrow_balance_positive_when_active');
        } elseif ($dbDriver === 'mysql') {
            try {
                DB::statement('ALTER TABLE businesses DROP CHECK chk_businesses_escrow_balance_positive_when_active');
            } catch (\Exception $e) {
                // Ignore if constraint doesn't exist
            }
        }
    }
};
