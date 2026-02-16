<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds 'cancelled' status so pending/processing jobs can be voided to allow re-inclusion in next payroll run.
     */
    public function up(): void
    {
        $dbDriver = DB::getDriverName();

        if ($dbDriver === 'mysql') {
            DB::statement("ALTER TABLE payroll_jobs MODIFY COLUMN status ENUM('pending', 'processing', 'succeeded', 'failed', 'cancelled') NOT NULL DEFAULT 'pending'");
            DB::statement('ALTER TABLE payroll_jobs DROP CHECK chk_payroll_jobs_status_valid');
            DB::statement("ALTER TABLE payroll_jobs ADD CONSTRAINT chk_payroll_jobs_status_valid CHECK (status IN ('pending', 'processing', 'succeeded', 'failed', 'cancelled'))");
        } elseif ($dbDriver === 'pgsql') {
            DB::statement('ALTER TABLE payroll_jobs DROP CONSTRAINT IF EXISTS chk_payroll_jobs_status_valid');
            DB::statement("ALTER TABLE payroll_jobs ADD CONSTRAINT chk_payroll_jobs_status_valid CHECK (status IN ('pending', 'processing', 'succeeded', 'failed', 'cancelled'))");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $dbDriver = DB::getDriverName();

        if ($dbDriver === 'mysql') {
            DB::statement('ALTER TABLE payroll_jobs DROP CHECK chk_payroll_jobs_status_valid');
            DB::statement("ALTER TABLE payroll_jobs MODIFY COLUMN status ENUM('pending', 'processing', 'succeeded', 'failed') NOT NULL DEFAULT 'pending'");
            DB::statement("ALTER TABLE payroll_jobs ADD CONSTRAINT chk_payroll_jobs_status_valid CHECK (status IN ('pending', 'processing', 'succeeded', 'failed'))");
        } elseif ($dbDriver === 'pgsql') {
            DB::statement('ALTER TABLE payroll_jobs DROP CONSTRAINT IF EXISTS chk_payroll_jobs_status_valid');
            DB::statement("ALTER TABLE payroll_jobs ADD CONSTRAINT chk_payroll_jobs_status_valid CHECK (status IN ('pending', 'processing', 'succeeded', 'failed'))");
        }
    }
};
