<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds a trigger to validate net_salary calculation integrity at database level.
     * This provides an additional safety layer beyond application-level validation.
     */
    public function up(): void
    {
        // For MySQL 8.0.16+, try adding a CHECK constraint
        // For older MySQL versions, this will be ignored and we'll use a trigger instead
        $dbDriver = DB::getDriverName();

        if ($dbDriver === 'mysql') {
            // Try CHECK constraint first (MySQL 8.0.16+)
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
                // If CHECK constraint fails (older MySQL), use trigger instead
                // Trigger validates net_salary calculation on INSERT/UPDATE
                DB::unprepared("
                    CREATE TRIGGER trg_validate_payroll_calculation_before_insert
                    BEFORE INSERT ON payroll_jobs
                    FOR EACH ROW
                    BEGIN
                        DECLARE calculated_net DECIMAL(15,2);
                        DECLARE adjustment_total DECIMAL(15,2) DEFAULT 0;
                        
                        -- Calculate expected net salary
                        SET calculated_net = NEW.gross_salary - NEW.paye_amount - NEW.uif_amount;
                        
                        -- Calculate adjustments total (if adjustments is JSON array)
                        -- Note: This is simplified - full adjustment calculation would require parsing JSON
                        -- For now, we validate that net_salary is within reasonable bounds
                        
                        IF NEW.net_salary < 0 THEN
                            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Net salary cannot be negative';
                        END IF;
                        
                        IF NEW.net_salary > NEW.gross_salary THEN
                            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Net salary cannot exceed gross salary';
                        END IF;
                        
                        -- Allow small rounding differences (up to 0.01 for rounding errors)
                        IF ABS(NEW.net_salary - calculated_net) > 0.01 THEN
                            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Net salary calculation mismatch exceeds tolerance';
                        END IF;
                    END
                ");

                DB::unprepared("
                    CREATE TRIGGER trg_validate_payroll_calculation_before_update
                    BEFORE UPDATE ON payroll_jobs
                    FOR EACH ROW
                    BEGIN
                        -- Only validate if net_salary is being changed
                        IF NEW.net_salary != OLD.net_salary THEN
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
                        END IF;
                    END
                ");
            }
        } elseif ($dbDriver === 'pgsql') {
            // PostgreSQL supports CHECK constraints natively
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
        // For SQLite and other databases, rely on application-level validation
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $dbDriver = DB::getDriverName();

        if ($dbDriver === 'mysql') {
            // Drop CHECK constraint if it exists
            try {
                DB::statement('ALTER TABLE payroll_jobs DROP CHECK chk_net_salary_calculation');
            } catch (\Exception $e) {
                // If CHECK constraint doesn't exist, drop triggers instead
                DB::unprepared('DROP TRIGGER IF EXISTS trg_validate_payroll_calculation_before_insert');
                DB::unprepared('DROP TRIGGER IF EXISTS trg_validate_payroll_calculation_before_update');
            }
        } elseif ($dbDriver === 'pgsql') {
            DB::statement('ALTER TABLE payroll_jobs DROP CONSTRAINT IF EXISTS chk_net_salary_calculation');
        }
    }
};
