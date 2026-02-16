<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Creates a partial unique index to prevent duplicate payroll jobs for active statuses only.
     * This allows failed jobs to be retried without violating uniqueness.
     */
    public function up(): void
    {
        $dbDriver = DB::getDriverName();

        // Drop existing unique constraint that doesn't account for status (if it exists)
        try {
            Schema::table('payroll_jobs', function (Blueprint $table) {
                $table->dropUnique('payroll_jobs_employee_period_unique');
            });
        } catch (\Exception $e) {
            // Constraint doesn't exist, continue
        }

        if ($dbDriver === 'mysql') {
            // MySQL has issues with generated columns that reference foreign key columns
            // Use trigger-based approach instead for MySQL
            $this->createUniquenessTrigger();

            // Also create a regular composite index for performance (non-unique)
            // The trigger will enforce uniqueness for active jobs
            Schema::table('payroll_jobs', function (Blueprint $table) {
                $table->index(['employee_id', 'pay_period_start', 'pay_period_end', 'status'], 'idx_payroll_jobs_period_status');
            });
        } elseif ($dbDriver === 'pgsql') {
            // PostgreSQL supports partial unique indexes natively
            DB::statement('
                CREATE UNIQUE INDEX idx_payroll_jobs_active_period_unique 
                ON payroll_jobs(employee_id, pay_period_start, pay_period_end)
                WHERE status IN (\'pending\', \'processing\', \'succeeded\')
            ');
        } else {
            // For other databases, use a trigger-based approach
            $this->createUniquenessTrigger();
        }
    }

    /**
     * Create a trigger to enforce uniqueness for active jobs
     * Used for MySQL and databases that don't support partial unique indexes
     */
    protected function createUniquenessTrigger(): void
    {
        // Drop trigger if it exists (for re-running migrations)
        DB::unprepared('DROP TRIGGER IF EXISTS trg_prevent_duplicate_active_payroll_jobs');

        DB::unprepared("
            CREATE TRIGGER trg_prevent_duplicate_active_payroll_jobs
            BEFORE INSERT ON payroll_jobs
            FOR EACH ROW
            BEGIN
                DECLARE existing_count INT DEFAULT 0;
                
                IF NEW.status IN ('pending', 'processing', 'succeeded') THEN
                    SELECT COUNT(*) INTO existing_count
                    FROM payroll_jobs
                    WHERE employee_id = NEW.employee_id
                      AND pay_period_start = NEW.pay_period_start
                      AND pay_period_end = NEW.pay_period_end
                      AND status IN ('pending', 'processing', 'succeeded')
                      AND id != COALESCE(NEW.id, 0);
                    
                    IF existing_count > 0 THEN
                        SIGNAL SQLSTATE '23000' 
                        SET MESSAGE_TEXT = 'Duplicate payroll job: Active job already exists for this employee and period';
                    END IF;
                END IF;
            END
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $dbDriver = DB::getDriverName();

        if ($dbDriver === 'mysql') {
            // Drop the trigger and index
            DB::unprepared('DROP TRIGGER IF EXISTS trg_prevent_duplicate_active_payroll_jobs');
            Schema::table('payroll_jobs', function (Blueprint $table) {
                $table->dropIndex('idx_payroll_jobs_period_status');
            });
        } elseif ($dbDriver === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS idx_payroll_jobs_active_period_unique');
        } else {
            DB::unprepared('DROP TRIGGER IF EXISTS trg_prevent_duplicate_active_payroll_jobs');
        }

        // Restore original unique constraint
        Schema::table('payroll_jobs', function (Blueprint $table) {
            $table->unique(['employee_id', 'pay_period_start', 'pay_period_end'], 'payroll_jobs_employee_period_unique');
        });
    }
};
