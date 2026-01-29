<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds CHECK constraints to ensure job amounts are always positive.
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

            // Payment jobs: amount must be positive
            $addConstraintIfNotExists('payment_jobs', 'chk_payment_jobs_amount_positive', 'amount > 0');

            // Payroll jobs: net_salary must be positive (what's actually paid)
            $addConstraintIfNotExists('payroll_jobs', 'chk_payroll_jobs_net_salary_positive', 'net_salary > 0');

            // Payroll jobs: gross_salary must be positive
            $addConstraintIfNotExists('payroll_jobs', 'chk_payroll_jobs_gross_salary_positive', 'gross_salary > 0');
        } elseif ($dbDriver === 'mysql') {
            // MySQL 8.0.16+ supports CHECK constraints
            try {
                DB::statement('
                    ALTER TABLE payment_jobs 
                    ADD CONSTRAINT chk_payment_jobs_amount_positive 
                    CHECK (amount > 0)
                ');

                DB::statement('
                    ALTER TABLE payroll_jobs 
                    ADD CONSTRAINT chk_payroll_jobs_net_salary_positive 
                    CHECK (net_salary > 0)
                ');

                DB::statement('
                    ALTER TABLE payroll_jobs 
                    ADD CONSTRAINT chk_payroll_jobs_gross_salary_positive 
                    CHECK (gross_salary > 0)
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
            DB::statement('ALTER TABLE payment_jobs DROP CONSTRAINT IF EXISTS chk_payment_jobs_amount_positive');
            DB::statement('ALTER TABLE payroll_jobs DROP CONSTRAINT IF EXISTS chk_payroll_jobs_net_salary_positive');
            DB::statement('ALTER TABLE payroll_jobs DROP CONSTRAINT IF EXISTS chk_payroll_jobs_gross_salary_positive');
        } elseif ($dbDriver === 'mysql') {
            try {
                DB::statement('ALTER TABLE payment_jobs DROP CHECK chk_payment_jobs_amount_positive');
                DB::statement('ALTER TABLE payroll_jobs DROP CHECK chk_payroll_jobs_net_salary_positive');
                DB::statement('ALTER TABLE payroll_jobs DROP CHECK chk_payroll_jobs_gross_salary_positive');
            } catch (\Exception $e) {
                // Ignore if constraints don't exist
            }
        }
    }
};
