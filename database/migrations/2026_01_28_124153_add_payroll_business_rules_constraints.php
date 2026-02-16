<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds CHECK constraints to enforce business rules at database level.
     */
    public function up(): void
    {
        $dbDriver = DB::getDriverName();

        if ($dbDriver === 'mysql') {
            // MySQL 8.0.16+ supports CHECK constraints
            // Add constraints for payroll_jobs table
            DB::statement('
                ALTER TABLE payroll_jobs
                ADD CONSTRAINT chk_payroll_jobs_gross_salary_non_negative
                CHECK (gross_salary >= 0)
            ');

            DB::statement('
                ALTER TABLE payroll_jobs
                ADD CONSTRAINT chk_payroll_jobs_paye_non_negative
                CHECK (paye_amount >= 0)
            ');

            DB::statement('
                ALTER TABLE payroll_jobs
                ADD CONSTRAINT chk_payroll_jobs_uif_non_negative
                CHECK (uif_amount >= 0)
            ');

            DB::statement('
                ALTER TABLE payroll_jobs
                ADD CONSTRAINT chk_payroll_jobs_sdl_non_negative
                CHECK (sdl_amount >= 0)
            ');

            DB::statement('
                ALTER TABLE payroll_jobs
                ADD CONSTRAINT chk_payroll_jobs_net_salary_non_negative
                CHECK (net_salary >= 0)
            ');

            DB::statement('
                ALTER TABLE payroll_jobs
                ADD CONSTRAINT chk_payroll_jobs_fee_non_negative
                CHECK (fee IS NULL OR fee >= 0)
            ');

            DB::statement('
                ALTER TABLE payroll_jobs
                ADD CONSTRAINT chk_payroll_jobs_period_valid
                CHECK (pay_period_start IS NOT NULL AND pay_period_end IS NOT NULL AND pay_period_start <= pay_period_end)
            ');

            DB::statement('
                ALTER TABLE payroll_jobs
                ADD CONSTRAINT chk_payroll_jobs_status_valid
                CHECK (status IN (\'pending\', \'processing\', \'succeeded\', \'failed\'))
            ');

            // Add constraints for businesses table
            DB::statement('
                ALTER TABLE businesses
                ADD CONSTRAINT chk_businesses_escrow_balance_non_negative
                CHECK (escrow_balance IS NULL OR escrow_balance >= 0)
            ');

            // Add constraints for escrow_deposits table
            DB::statement('
                ALTER TABLE escrow_deposits
                ADD CONSTRAINT chk_escrow_deposits_amount_positive
                CHECK (amount > 0)
            ');

            DB::statement('
                ALTER TABLE escrow_deposits
                ADD CONSTRAINT chk_escrow_deposits_fee_non_negative
                CHECK (fee_amount >= 0)
            ');

            DB::statement('
                ALTER TABLE escrow_deposits
                ADD CONSTRAINT chk_escrow_deposits_authorized_non_negative
                CHECK (authorized_amount >= 0)
            ');

            DB::statement('
                ALTER TABLE escrow_deposits
                ADD CONSTRAINT chk_escrow_deposits_status_valid
                CHECK (status IN (\'pending\', \'confirmed\', \'cancelled\'))
            ');

        } elseif ($dbDriver === 'pgsql') {
            // PostgreSQL: ADD CONSTRAINT ... CHECK (Laravel Blueprint has no check() method)
            DB::statement('ALTER TABLE payroll_jobs ADD CONSTRAINT chk_payroll_jobs_gross_salary_non_negative CHECK (gross_salary >= 0)');
            DB::statement('ALTER TABLE payroll_jobs ADD CONSTRAINT chk_payroll_jobs_paye_non_negative CHECK (paye_amount >= 0)');
            DB::statement('ALTER TABLE payroll_jobs ADD CONSTRAINT chk_payroll_jobs_uif_non_negative CHECK (uif_amount >= 0)');
            DB::statement('ALTER TABLE payroll_jobs ADD CONSTRAINT chk_payroll_jobs_sdl_non_negative CHECK (sdl_amount >= 0)');
            DB::statement('ALTER TABLE payroll_jobs ADD CONSTRAINT chk_payroll_jobs_net_salary_non_negative CHECK (net_salary >= 0)');
            DB::statement('ALTER TABLE payroll_jobs ADD CONSTRAINT chk_payroll_jobs_fee_non_negative CHECK (fee IS NULL OR fee >= 0)');
            DB::statement('ALTER TABLE payroll_jobs ADD CONSTRAINT chk_payroll_jobs_period_valid CHECK (pay_period_start IS NOT NULL AND pay_period_end IS NOT NULL AND pay_period_start <= pay_period_end)');
            DB::statement("ALTER TABLE payroll_jobs ADD CONSTRAINT chk_payroll_jobs_status_valid CHECK (status IN ('pending', 'processing', 'succeeded', 'failed'))");

            DB::statement('ALTER TABLE businesses ADD CONSTRAINT chk_businesses_escrow_balance_non_negative CHECK (escrow_balance IS NULL OR escrow_balance >= 0)');

            DB::statement('ALTER TABLE escrow_deposits ADD CONSTRAINT chk_escrow_deposits_amount_positive CHECK (amount > 0)');
            DB::statement('ALTER TABLE escrow_deposits ADD CONSTRAINT chk_escrow_deposits_fee_non_negative CHECK (fee_amount >= 0)');
            DB::statement('ALTER TABLE escrow_deposits ADD CONSTRAINT chk_escrow_deposits_authorized_non_negative CHECK (authorized_amount >= 0)');
            DB::statement("ALTER TABLE escrow_deposits ADD CONSTRAINT chk_escrow_deposits_status_valid CHECK (status IN ('pending', 'confirmed', 'cancelled'))");
        }
        // For other databases, rely on application-level validation
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $dbDriver = DB::getDriverName();

        if ($dbDriver === 'mysql') {
            DB::statement('ALTER TABLE payroll_jobs DROP CHECK chk_payroll_jobs_gross_salary_non_negative');
            DB::statement('ALTER TABLE payroll_jobs DROP CHECK chk_payroll_jobs_paye_non_negative');
            DB::statement('ALTER TABLE payroll_jobs DROP CHECK chk_payroll_jobs_uif_non_negative');
            DB::statement('ALTER TABLE payroll_jobs DROP CHECK chk_payroll_jobs_sdl_non_negative');
            DB::statement('ALTER TABLE payroll_jobs DROP CHECK chk_payroll_jobs_net_salary_non_negative');
            DB::statement('ALTER TABLE payroll_jobs DROP CHECK chk_payroll_jobs_fee_non_negative');
            DB::statement('ALTER TABLE payroll_jobs DROP CHECK chk_payroll_jobs_period_valid');
            DB::statement('ALTER TABLE payroll_jobs DROP CHECK chk_payroll_jobs_status_valid');
            DB::statement('ALTER TABLE businesses DROP CHECK chk_businesses_escrow_balance_non_negative');
            DB::statement('ALTER TABLE escrow_deposits DROP CHECK chk_escrow_deposits_amount_positive');
            DB::statement('ALTER TABLE escrow_deposits DROP CHECK chk_escrow_deposits_fee_non_negative');
            DB::statement('ALTER TABLE escrow_deposits DROP CHECK chk_escrow_deposits_authorized_non_negative');
            DB::statement('ALTER TABLE escrow_deposits DROP CHECK chk_escrow_deposits_status_valid');
        } elseif ($dbDriver === 'pgsql') {
            DB::statement('ALTER TABLE payroll_jobs DROP CONSTRAINT IF EXISTS chk_payroll_jobs_gross_salary_non_negative');
            DB::statement('ALTER TABLE payroll_jobs DROP CONSTRAINT IF EXISTS chk_payroll_jobs_paye_non_negative');
            DB::statement('ALTER TABLE payroll_jobs DROP CONSTRAINT IF EXISTS chk_payroll_jobs_uif_non_negative');
            DB::statement('ALTER TABLE payroll_jobs DROP CONSTRAINT IF EXISTS chk_payroll_jobs_sdl_non_negative');
            DB::statement('ALTER TABLE payroll_jobs DROP CONSTRAINT IF EXISTS chk_payroll_jobs_net_salary_non_negative');
            DB::statement('ALTER TABLE payroll_jobs DROP CONSTRAINT IF EXISTS chk_payroll_jobs_fee_non_negative');
            DB::statement('ALTER TABLE payroll_jobs DROP CONSTRAINT IF EXISTS chk_payroll_jobs_period_valid');
            DB::statement('ALTER TABLE payroll_jobs DROP CONSTRAINT IF EXISTS chk_payroll_jobs_status_valid');
            DB::statement('ALTER TABLE businesses DROP CONSTRAINT IF EXISTS chk_businesses_escrow_balance_non_negative');
            DB::statement('ALTER TABLE escrow_deposits DROP CONSTRAINT IF EXISTS chk_escrow_deposits_amount_positive');
            DB::statement('ALTER TABLE escrow_deposits DROP CONSTRAINT IF EXISTS chk_escrow_deposits_fee_non_negative');
            DB::statement('ALTER TABLE escrow_deposits DROP CONSTRAINT IF EXISTS chk_escrow_deposits_authorized_non_negative');
            DB::statement('ALTER TABLE escrow_deposits DROP CONSTRAINT IF EXISTS chk_escrow_deposits_status_valid');
        }
    }
};
