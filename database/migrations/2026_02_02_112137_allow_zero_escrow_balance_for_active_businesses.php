<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Relax constraint so active businesses may have escrow_balance >= 0 (allow R 0.00 for new businesses).
     */
    public function up(): void
    {
        $dbDriver = DB::getDriverName();

        if ($dbDriver === 'pgsql') {
            DB::statement('ALTER TABLE businesses DROP CONSTRAINT IF EXISTS chk_businesses_escrow_balance_positive_when_active');

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
                            escrow_balance >= 0
                        );
                    END IF;
                END \$\$;
            ");
        } elseif ($dbDriver === 'mysql') {
            try {
                DB::statement('ALTER TABLE businesses DROP CHECK chk_businesses_escrow_balance_positive_when_active');
            } catch (\Exception $e) {
                // Ignore if constraint does not exist
            }
            try {
                DB::statement('
                    ALTER TABLE businesses
                    ADD CONSTRAINT chk_businesses_escrow_balance_positive_when_active
                    CHECK (
                        status != \'active\' OR
                        escrow_balance IS NULL OR
                        escrow_balance >= 0
                    )
                ');
            } catch (\Exception $e) {
                // Older MySQL versions may not support CHECK constraints
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
            try {
                DB::statement('ALTER TABLE businesses DROP CHECK chk_businesses_escrow_balance_positive_when_active');
            } catch (\Exception $e) {
                //
            }
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
                //
            }
        }
    }
};
