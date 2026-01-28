<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds database-level trigger to prevent period overlaps for active payroll jobs.
     * This provides an additional safety layer beyond application-level validation.
     */
    public function up(): void
    {
        $dbDriver = DB::getDriverName();

        if ($dbDriver === 'mysql') {
            // MySQL trigger to prevent period overlaps
            DB::unprepared("
                CREATE TRIGGER trg_prevent_period_overlap_before_insert
                BEFORE INSERT ON payroll_jobs
                FOR EACH ROW
                BEGIN
                    DECLARE overlapping_count INT DEFAULT 0;
                    
                    -- Only check for overlaps if status is active
                    IF NEW.status IN ('pending', 'processing', 'succeeded') THEN
                        SELECT COUNT(*) INTO overlapping_count
                        FROM payroll_jobs
                        WHERE employee_id = NEW.employee_id
                          AND status IN ('pending', 'processing', 'succeeded')
                          AND (
                              -- New period starts within existing period
                              (NEW.pay_period_start >= pay_period_start AND NEW.pay_period_start <= pay_period_end)
                              OR
                              -- New period ends within existing period
                              (NEW.pay_period_end >= pay_period_start AND NEW.pay_period_end <= pay_period_end)
                              OR
                              -- New period completely contains existing period
                              (NEW.pay_period_start <= pay_period_start AND NEW.pay_period_end >= pay_period_end)
                              OR
                              -- Existing period completely contains new period
                              (pay_period_start <= NEW.pay_period_start AND pay_period_end >= NEW.pay_period_end)
                          );
                        
                        IF overlapping_count > 0 THEN
                            SIGNAL SQLSTATE '23000' 
                            SET MESSAGE_TEXT = 'Period overlap detected: Active payroll job already exists for overlapping period';
                        END IF;
                    END IF;
                END
            ");

            DB::unprepared("
                CREATE TRIGGER trg_prevent_period_overlap_before_update
                BEFORE UPDATE ON payroll_jobs
                FOR EACH ROW
                BEGIN
                    DECLARE overlapping_count INT DEFAULT 0;
                    
                    -- Only check if period dates or status are being changed
                    IF (NEW.pay_period_start != OLD.pay_period_start 
                        OR NEW.pay_period_end != OLD.pay_period_end
                        OR NEW.status != OLD.status) THEN
                        
                        -- Only check for overlaps if new status is active
                        IF NEW.status IN ('pending', 'processing', 'succeeded') THEN
                            SELECT COUNT(*) INTO overlapping_count
                            FROM payroll_jobs
                            WHERE employee_id = NEW.employee_id
                              AND id != NEW.id
                              AND status IN ('pending', 'processing', 'succeeded')
                              AND (
                                  -- New period starts within existing period
                                  (NEW.pay_period_start >= pay_period_start AND NEW.pay_period_start <= pay_period_end)
                                  OR
                                  -- New period ends within existing period
                                  (NEW.pay_period_end >= pay_period_start AND NEW.pay_period_end <= pay_period_end)
                                  OR
                                  -- New period completely contains existing period
                                  (NEW.pay_period_start <= pay_period_start AND NEW.pay_period_end >= pay_period_end)
                                  OR
                                  -- Existing period completely contains new period
                                  (pay_period_start <= NEW.pay_period_start AND pay_period_end >= NEW.pay_period_end)
                              );
                            
                            IF overlapping_count > 0 THEN
                                SIGNAL SQLSTATE '23000' 
                                SET MESSAGE_TEXT = 'Period overlap detected: Active payroll job already exists for overlapping period';
                            END IF;
                        END IF;
                    END IF;
                END
            ");
        } elseif ($dbDriver === 'pgsql') {
            // PostgreSQL trigger (similar logic)
            DB::unprepared("
                CREATE OR REPLACE FUNCTION prevent_period_overlap()
                RETURNS TRIGGER AS $$
                DECLARE
                    overlapping_count INTEGER;
                BEGIN
                    IF NEW.status IN ('pending', 'processing', 'succeeded') THEN
                        SELECT COUNT(*) INTO overlapping_count
                        FROM payroll_jobs
                        WHERE employee_id = NEW.employee_id
                          AND status IN ('pending', 'processing', 'succeeded')
                          AND (TG_OP = 'INSERT' OR id != NEW.id)
                          AND (
                              (NEW.pay_period_start >= pay_period_start AND NEW.pay_period_start <= pay_period_end)
                              OR
                              (NEW.pay_period_end >= pay_period_start AND NEW.pay_period_end <= pay_period_end)
                              OR
                              (NEW.pay_period_start <= pay_period_start AND NEW.pay_period_end >= pay_period_end)
                              OR
                              (pay_period_start <= NEW.pay_period_start AND pay_period_end >= NEW.pay_period_end)
                          );
                        
                        IF overlapping_count > 0 THEN
                            RAISE EXCEPTION 'Period overlap detected: Active payroll job already exists for overlapping period';
                        END IF;
                    END IF;
                    
                    RETURN NEW;
                END;
                $$ LANGUAGE plpgsql;
            ");

            DB::unprepared('
                CREATE TRIGGER trg_prevent_period_overlap_before_insert
                BEFORE INSERT ON payroll_jobs
                FOR EACH ROW
                EXECUTE FUNCTION prevent_period_overlap();
            ');

            DB::unprepared('
                CREATE TRIGGER trg_prevent_period_overlap_before_update
                BEFORE UPDATE ON payroll_jobs
                FOR EACH ROW
                WHEN (
                    OLD.pay_period_start IS DISTINCT FROM NEW.pay_period_start
                    OR OLD.pay_period_end IS DISTINCT FROM NEW.pay_period_end
                    OR OLD.status IS DISTINCT FROM NEW.status
                )
                EXECUTE FUNCTION prevent_period_overlap();
            ');
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
            DB::unprepared('DROP TRIGGER IF EXISTS trg_prevent_period_overlap_before_insert');
            DB::unprepared('DROP TRIGGER IF EXISTS trg_prevent_period_overlap_before_update');
        } elseif ($dbDriver === 'pgsql') {
            DB::unprepared('DROP TRIGGER IF EXISTS trg_prevent_period_overlap_before_insert ON payroll_jobs');
            DB::unprepared('DROP TRIGGER IF EXISTS trg_prevent_period_overlap_before_update ON payroll_jobs');
            DB::unprepared('DROP FUNCTION IF EXISTS prevent_period_overlap()');
        }
    }
};
