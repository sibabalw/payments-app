<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Fixes the net_salary calculation constraint to account for adjustments.
     * The original constraint was too strict and didn't account for adjustments
     * (deductions/additions) that are applied after statutory deductions.
     */
    public function up(): void
    {
        $dbDriver = DB::getDriverName();

        if ($dbDriver === 'mysql') {
            // Drop the old constraint that doesn't account for adjustments
            try {
                DB::statement('ALTER TABLE payroll_jobs DROP CHECK chk_net_salary_calculation');
            } catch (\Exception $e) {
                // Constraint might not exist or might be a trigger, try dropping triggers
                DB::unprepared('DROP TRIGGER IF EXISTS trg_validate_payroll_calculation_before_insert');
                DB::unprepared('DROP TRIGGER IF EXISTS trg_validate_payroll_calculation_before_update');
            }

            // Add a more lenient constraint that only validates bounds
            // This accounts for adjustments which can vary and are stored as JSON
            try {
                DB::statement('
                    ALTER TABLE payroll_jobs
                    ADD CONSTRAINT chk_net_salary_calculation
                    CHECK (
                        net_salary >= 0 AND
                        net_salary <= gross_salary
                    )
                ');
            } catch (\Exception $e) {
                // If CHECK constraint fails, use trigger instead
                DB::unprepared("
                    CREATE TRIGGER trg_validate_payroll_calculation_before_insert
                    BEFORE INSERT ON payroll_jobs
                    FOR EACH ROW
                    BEGIN
                        IF NEW.net_salary < 0 THEN
                            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Net salary cannot be negative';
                        END IF;
                        
                        IF NEW.net_salary > NEW.gross_salary THEN
                            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Net salary cannot exceed gross salary';
                        END IF;
                    END
                ");

                DB::unprepared("
                    CREATE TRIGGER trg_validate_payroll_calculation_before_update
                    BEFORE UPDATE ON payroll_jobs
                    FOR EACH ROW
                    BEGIN
                        IF NEW.net_salary != OLD.net_salary THEN
                            IF NEW.net_salary < 0 THEN
                                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Net salary cannot be negative';
                            END IF;
                            
                            IF NEW.net_salary > NEW.gross_salary THEN
                                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Net salary cannot exceed gross salary';
                            END IF;
                        END IF;
                    END
                ");
            }
        } elseif ($dbDriver === 'pgsql') {
            // Drop old constraint
            DB::statement('ALTER TABLE payroll_jobs DROP CONSTRAINT IF EXISTS chk_net_salary_calculation');

            // Add new constraint
            DB::statement('
                ALTER TABLE payroll_jobs
                ADD CONSTRAINT chk_net_salary_calculation
                CHECK (
                    net_salary >= 0 AND
                    net_salary <= gross_salary
                )
            ');
        }
        // For SQLite and other databases, rely on application-level validation
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $dbDriver = DB::getDriverName();

        if ($dbDriver === 'mysql') {
            // Drop the new constraint
            try {
                DB::statement('ALTER TABLE payroll_jobs DROP CHECK chk_net_salary_calculation');
            } catch (\Exception $e) {
                // If CHECK constraint doesn't exist, drop triggers instead
                DB::unprepared('DROP TRIGGER IF EXISTS trg_validate_payroll_calculation_before_insert');
                DB::unprepared('DROP TRIGGER IF EXISTS trg_validate_payroll_calculation_before_update');
            }

            // Restore the old constraint (with calculation check)
            try {
                DB::statement('
                    ALTER TABLE payroll_jobs
                    ADD CONSTRAINT chk_net_salary_calculation
                    CHECK (
                        net_salary >= 0 AND
                        net_salary <= gross_salary AND
                        ABS(net_salary - (gross_salary - paye_amount - uif_amount)) <= 0.01
                    )
                ');
            } catch (\Exception $e) {
                // If CHECK constraint fails, use trigger instead
                DB::unprepared("
                    CREATE TRIGGER trg_validate_payroll_calculation_before_insert
                    BEFORE INSERT ON payroll_jobs
                    FOR EACH ROW
                    BEGIN
                        DECLARE calculated_net DECIMAL(15,2);
                        SET calculated_net = NEW.gross_salary - NEW.paye_amount - NEW.uif_amount;
                        
                        IF NEW.net_salary < 0 THEN
                            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Net salary cannot be negative';
                        END IF;
                        
                        IF NEW.net_salary > NEW.gross_salary THEN
                            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Net salary cannot exceed gross salary';
                        END IF;
                        
                        IF ABS(NEW.net_salary - calculated_net) > 0.01 THEN
                            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Net salary calculation mismatch exceeds tolerance';
                        END IF;
                    END
                ");
            }
        } elseif ($dbDriver === 'pgsql') {
            DB::statement('ALTER TABLE payroll_jobs DROP CONSTRAINT IF EXISTS chk_net_salary_calculation');
            DB::statement('
                ALTER TABLE payroll_jobs
                ADD CONSTRAINT chk_net_salary_calculation
                CHECK (
                    net_salary >= 0 AND
                    net_salary <= gross_salary AND
                    ABS(net_salary - (gross_salary - paye_amount - uif_amount)) <= 0.01
                )
            ');
        }
    }
};
