<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Optimizes PostgreSQL indexes with covering indexes and GIN indexes for JSONB.
     */
    public function up(): void
    {
        $dbDriver = DB::getDriverName();

        if ($dbDriver === 'pgsql') {
            // Add GIN index on financial_ledger metadata (JSONB) for fast searches
            // Only create if column is jsonb type (GIN indexes require jsonb, not json)
            DB::unprepared("
                DO \$\$
                BEGIN
                    IF EXISTS (
                        SELECT 1 FROM information_schema.columns 
                        WHERE table_name = 'financial_ledger' 
                        AND column_name = 'metadata' 
                        AND data_type = 'jsonb'
                    ) THEN
                        CREATE INDEX IF NOT EXISTS idx_financial_ledger_metadata_gin 
                        ON financial_ledger USING GIN (metadata);
                    END IF;
                END \$\$;
            ");

            // Add covering index for balance queries (includes amount_minor_units)
            DB::statement('
                CREATE INDEX IF NOT EXISTS idx_financial_ledger_balance_covering 
                ON financial_ledger (business_id, account_type, posting_state, transaction_type) 
                INCLUDE (amount, amount_minor_units, sequence_number)
            ');

            // Add covering index for correlation_id lookups
            DB::statement('
                CREATE INDEX IF NOT EXISTS idx_financial_ledger_correlation_covering 
                ON financial_ledger (correlation_id) 
                INCLUDE (transaction_type, account_type, amount, posting_state, effective_at)
            ');

            // Add covering index for payroll jobs status queries
            DB::statement('
                CREATE INDEX IF NOT EXISTS idx_payroll_jobs_status_covering 
                ON payroll_jobs (status, payroll_schedule_id) 
                INCLUDE (id, employee_id, net_salary, pay_period_start, pay_period_end, processed_at)
            ');

            // Add covering index for payment jobs status queries
            DB::statement('
                CREATE INDEX IF NOT EXISTS idx_payment_jobs_status_covering 
                ON payment_jobs (status, payment_schedule_id) 
                INCLUDE (id, recipient_id, amount, processed_at)
            ');

            // Add composite index for business balance queries
            DB::statement('
                CREATE INDEX IF NOT EXISTS idx_businesses_balance_covering 
                ON businesses (id, status) 
                INCLUDE (escrow_balance, hold_amount)
            ');

            // Add index for reconciliation queries (stuck jobs detection)
            DB::statement('
                CREATE INDEX IF NOT EXISTS idx_payroll_jobs_stuck_detection 
                ON payroll_jobs (status, updated_at) 
                WHERE status = \'processing\'
            ');

            DB::statement('
                CREATE INDEX IF NOT EXISTS idx_payment_jobs_stuck_detection 
                ON payment_jobs (status, updated_at) 
                WHERE status = \'processing\'
            ');

            // Add index for operation chain queries (metadata->operation_id)
            // Only create if column is jsonb type (GIN indexes require jsonb, not json)
            DB::unprepared("
                DO \$\$
                BEGIN
                    IF EXISTS (
                        SELECT 1 FROM information_schema.columns 
                        WHERE table_name = 'financial_ledger' 
                        AND column_name = 'metadata' 
                        AND data_type = 'jsonb'
                    ) THEN
                        CREATE INDEX IF NOT EXISTS idx_financial_ledger_operation_id 
                        ON financial_ledger USING GIN ((metadata->'operation_id'));
                    END IF;
                END \$\$;
            ");

            // Add index for compensation chain queries
            // Only create if column is jsonb type (GIN indexes require jsonb, not json)
            DB::unprepared("
                DO \$\$
                BEGIN
                    IF EXISTS (
                        SELECT 1 FROM information_schema.columns 
                        WHERE table_name = 'financial_ledger' 
                        AND column_name = 'metadata' 
                        AND data_type = 'jsonb'
                    ) THEN
                        CREATE INDEX IF NOT EXISTS idx_financial_ledger_compensation_chain 
                        ON financial_ledger USING GIN ((metadata->'compensation_chain_id'));
                    END IF;
                END \$\$;
            ");
        }
        // For MySQL and other databases, indexes are handled in other migrations
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $dbDriver = DB::getDriverName();

        if ($dbDriver === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS idx_financial_ledger_metadata_gin');
            DB::statement('DROP INDEX IF EXISTS idx_financial_ledger_balance_covering');
            DB::statement('DROP INDEX IF EXISTS idx_financial_ledger_correlation_covering');
            DB::statement('DROP INDEX IF EXISTS idx_payroll_jobs_status_covering');
            DB::statement('DROP INDEX IF EXISTS idx_payment_jobs_status_covering');
            DB::statement('DROP INDEX IF EXISTS idx_businesses_balance_covering');
            DB::statement('DROP INDEX IF EXISTS idx_payroll_jobs_stuck_detection');
            DB::statement('DROP INDEX IF EXISTS idx_payment_jobs_stuck_detection');
            DB::statement('DROP INDEX IF EXISTS idx_financial_ledger_operation_id');
            DB::statement('DROP INDEX IF EXISTS idx_financial_ledger_compensation_chain');
        }
    }
};
