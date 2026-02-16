<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds additional PostgreSQL CHECK constraints for business rules.
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

            // Payment jobs constraints
            $addConstraintIfNotExists('payment_jobs', 'chk_payment_jobs_amount_positive', 'amount > 0');
            $addConstraintIfNotExists('payment_jobs', 'chk_payment_jobs_fee_non_negative', 'fee IS NULL OR fee >= 0');
            $addConstraintIfNotExists('payment_jobs', 'chk_payment_jobs_status_valid', "status IN ('pending', 'processing', 'succeeded', 'failed')");

            // Financial ledger constraints
            $addConstraintIfNotExists('financial_ledger', 'chk_financial_ledger_amount_positive', 'amount > 0');
            $addConstraintIfNotExists('financial_ledger', 'chk_financial_ledger_amount_minor_units_positive', 'amount_minor_units > 0');
            $addConstraintIfNotExists('financial_ledger', 'chk_financial_ledger_transaction_type_valid', "transaction_type IN ('DEBIT', 'CREDIT')");
            $addConstraintIfNotExists('financial_ledger', 'chk_financial_ledger_posting_state_valid', "posting_state IN ('PENDING', 'POSTED', 'REVERSED')");
            $addConstraintIfNotExists('financial_ledger', 'chk_financial_ledger_reversal_not_self', 'reversal_of_id IS NULL OR reversal_of_id::bigint != id');
            $addConstraintIfNotExists('financial_ledger', 'chk_financial_ledger_sequence_positive', 'sequence_number > 0');

            // Businesses table - additional constraints
            $addConstraintIfNotExists('businesses', 'chk_businesses_hold_amount_non_negative', 'hold_amount IS NULL OR hold_amount >= 0');

            // Escrow deposits - ensure authorized_amount <= amount
            $addConstraintIfNotExists('escrow_deposits', 'chk_escrow_deposits_authorized_not_exceed_amount', 'authorized_amount <= amount');

            // Escrow deposits - ensure fee_amount + authorized_amount <= amount (with tolerance for rounding)
            $addConstraintIfNotExists('escrow_deposits', 'chk_escrow_deposits_fee_plus_authorized_valid', 'fee_amount + authorized_amount <= amount + 0.01');
        }
        // For MySQL and other databases, constraints are handled in other migrations
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $dbDriver = DB::getDriverName();

        if ($dbDriver === 'pgsql') {
            DB::statement('ALTER TABLE payment_jobs DROP CONSTRAINT IF EXISTS chk_payment_jobs_amount_positive');
            DB::statement('ALTER TABLE payment_jobs DROP CONSTRAINT IF EXISTS chk_payment_jobs_fee_non_negative');
            DB::statement('ALTER TABLE payment_jobs DROP CONSTRAINT IF EXISTS chk_payment_jobs_status_valid');
            DB::statement('ALTER TABLE financial_ledger DROP CONSTRAINT IF EXISTS chk_financial_ledger_amount_positive');
            DB::statement('ALTER TABLE financial_ledger DROP CONSTRAINT IF EXISTS chk_financial_ledger_amount_minor_units_positive');
            DB::statement('ALTER TABLE financial_ledger DROP CONSTRAINT IF EXISTS chk_financial_ledger_transaction_type_valid');
            DB::statement('ALTER TABLE financial_ledger DROP CONSTRAINT IF EXISTS chk_financial_ledger_posting_state_valid');
            DB::statement('ALTER TABLE financial_ledger DROP CONSTRAINT IF EXISTS chk_financial_ledger_reversal_not_self');
            DB::statement('ALTER TABLE financial_ledger DROP CONSTRAINT IF EXISTS chk_financial_ledger_sequence_positive');
            DB::statement('ALTER TABLE businesses DROP CONSTRAINT IF EXISTS chk_businesses_hold_amount_non_negative');
            DB::statement('ALTER TABLE escrow_deposits DROP CONSTRAINT IF EXISTS chk_escrow_deposits_authorized_not_exceed_amount');
            DB::statement('ALTER TABLE escrow_deposits DROP CONSTRAINT IF EXISTS chk_escrow_deposits_fee_plus_authorized_valid');
        }
    }
};
