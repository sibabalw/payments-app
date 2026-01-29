<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds PostgreSQL triggers to validate payment and payroll schedules
     * don't exceed available escrow balance when created or updated.
     */
    public function up(): void
    {
        $dbDriver = DB::getDriverName();

        if ($dbDriver === 'pgsql') {
            // Function to check escrow balance before payment schedule insertion/update
            DB::unprepared("
                CREATE OR REPLACE FUNCTION check_escrow_balance_before_payment_schedule()
                RETURNS TRIGGER AS \$\$
                DECLARE
                    business_record RECORD;
                    available_balance DECIMAL(15,2);
                    recipient_count INTEGER;
                    total_amount DECIMAL(15,2);
                BEGIN
                    -- Lock the business row to prevent concurrent modifications during check
                    SELECT b.escrow_balance, b.hold_amount, b.id
                    INTO business_record
                    FROM businesses b
                    WHERE b.id = NEW.business_id
                    FOR UPDATE OF b;
                    
                    -- Check if business exists
                    IF business_record.id IS NULL THEN
                        RAISE EXCEPTION 'Cannot create/update payment schedule: business not found';
                    END IF;
                    
                    -- Explicit check: escrow_balance must be NOT NULL
                    IF business_record.escrow_balance IS NULL THEN
                        RAISE EXCEPTION 'Cannot create/update payment schedule: escrow balance is NULL. Business ID: %', 
                            NEW.business_id;
                    END IF;
                    
                    -- Explicit check: escrow_balance must be greater than zero
                    IF business_record.escrow_balance = 0 THEN
                        RAISE EXCEPTION 'Cannot create/update payment schedule: escrow balance is zero. Business ID: %', 
                            NEW.business_id;
                    END IF;
                    
                    IF business_record.escrow_balance < 0 THEN
                        RAISE EXCEPTION 'Cannot create/update payment schedule: escrow balance is negative (%). Business ID: %', 
                            business_record.escrow_balance, NEW.business_id;
                    END IF;
                    
                    -- Calculate available balance (escrow_balance - hold_amount)
                    available_balance := business_record.escrow_balance - COALESCE(business_record.hold_amount, 0);
                    
                    -- Explicit check: available balance must be greater than zero
                    IF available_balance = 0 THEN
                        RAISE EXCEPTION 'Cannot create/update payment schedule: available balance is zero. Business ID: %', 
                            NEW.business_id;
                    END IF;
                    
                    IF available_balance < 0 THEN
                        RAISE EXCEPTION 'Cannot create/update payment schedule: available balance is negative (%). Business ID: %', 
                            available_balance, NEW.business_id;
                    END IF;
                    
                    -- Get recipient count for this schedule
                    -- For INSERT, count will be 0 initially (recipients attached after), so use 1 as minimum
                    -- For UPDATE, count existing recipients
                    SELECT COALESCE(COUNT(*), 0)
                    INTO recipient_count
                    FROM payment_schedule_recipient
                    WHERE payment_schedule_id = NEW.id;
                    
                    -- If no recipients yet (INSERT case), assume at least 1
                    IF recipient_count = 0 THEN
                        recipient_count := 1;
                    END IF;
                    
                    -- Calculate total amount required
                    total_amount := NEW.amount * recipient_count;
                    
                    -- Explicit check: available balance must be sufficient for total amount
                    IF available_balance < total_amount THEN
                        RAISE EXCEPTION 'Cannot create/update payment schedule: insufficient escrow balance. Available: %, Required: % (amount: % × recipients: %), Business ID: %', 
                            available_balance, total_amount, NEW.amount, recipient_count, NEW.business_id;
                    END IF;
                    
                    RETURN NEW;
                END;
                \$\$ LANGUAGE plpgsql;
            ");

            // Triggers for payment_schedules
            DB::unprepared('
                DROP TRIGGER IF EXISTS trg_check_escrow_balance_before_payment_schedule_insert ON payment_schedules;
                CREATE TRIGGER trg_check_escrow_balance_before_payment_schedule_insert
                BEFORE INSERT ON payment_schedules
                FOR EACH ROW
                EXECUTE FUNCTION check_escrow_balance_before_payment_schedule();
            ');

            DB::unprepared('
                DROP TRIGGER IF EXISTS trg_check_escrow_balance_before_payment_schedule_update ON payment_schedules;
                CREATE TRIGGER trg_check_escrow_balance_before_payment_schedule_update
                BEFORE UPDATE ON payment_schedules
                FOR EACH ROW
                EXECUTE FUNCTION check_escrow_balance_before_payment_schedule();
            ');

            // Function to check escrow balance before payroll schedule insertion/update
            DB::unprepared("
                CREATE OR REPLACE FUNCTION check_escrow_balance_before_payroll_schedule()
                RETURNS TRIGGER AS \$\$
                DECLARE
                    business_record RECORD;
                    available_balance DECIMAL(15,2);
                    total_estimate DECIMAL(15,2);
                BEGIN
                    -- Lock the business row to prevent concurrent modifications during check
                    SELECT b.escrow_balance, b.hold_amount, b.id
                    INTO business_record
                    FROM businesses b
                    WHERE b.id = NEW.business_id
                    FOR UPDATE OF b;
                    
                    -- Check if business exists
                    IF business_record.id IS NULL THEN
                        RAISE EXCEPTION 'Cannot create/update payroll schedule: business not found';
                    END IF;
                    
                    -- Explicit check: escrow_balance must be NOT NULL
                    IF business_record.escrow_balance IS NULL THEN
                        RAISE EXCEPTION 'Cannot create/update payroll schedule: escrow balance is NULL. Business ID: %', 
                            NEW.business_id;
                    END IF;
                    
                    -- Explicit check: escrow_balance must be greater than zero
                    IF business_record.escrow_balance = 0 THEN
                        RAISE EXCEPTION 'Cannot create/update payroll schedule: escrow balance is zero. Business ID: %', 
                            NEW.business_id;
                    END IF;
                    
                    IF business_record.escrow_balance < 0 THEN
                        RAISE EXCEPTION 'Cannot create/update payroll schedule: escrow balance is negative (%). Business ID: %', 
                            business_record.escrow_balance, NEW.business_id;
                    END IF;
                    
                    -- Calculate available balance (escrow_balance - hold_amount)
                    available_balance := business_record.escrow_balance - COALESCE(business_record.hold_amount, 0);
                    
                    -- Explicit check: available balance must be greater than zero
                    IF available_balance = 0 THEN
                        RAISE EXCEPTION 'Cannot create/update payroll schedule: available balance is zero. Business ID: %', 
                            NEW.business_id;
                    END IF;
                    
                    IF available_balance < 0 THEN
                        RAISE EXCEPTION 'Cannot create/update payroll schedule: available balance is negative (%). Business ID: %', 
                            available_balance, NEW.business_id;
                    END IF;
                    
                    -- Calculate total estimate based on gross salaries of employees in this schedule
                    -- Use gross_salary as conservative estimate (net will be less after deductions)
                    SELECT COALESCE(SUM(e.gross_salary), 0)
                    INTO total_estimate
                    FROM payroll_schedule_employee pse
                    JOIN employees e ON e.id = pse.employee_id
                    WHERE pse.payroll_schedule_id = NEW.id;
                    
                    -- If no employees yet (INSERT case), skip check (will be validated when employees are attached)
                    -- For UPDATE, validate if employees exist
                    IF total_estimate > 0 THEN
                        -- Explicit check: available balance must be sufficient for total estimate
                        IF available_balance < total_estimate THEN
                            RAISE EXCEPTION 'Cannot create/update payroll schedule: insufficient escrow balance. Available: %, Estimated required: % (based on gross salaries), Business ID: %', 
                                available_balance, total_estimate, NEW.business_id;
                        END IF;
                    END IF;
                    
                    RETURN NEW;
                END;
                \$\$ LANGUAGE plpgsql;
            ");

            // Triggers for payroll_schedules
            DB::unprepared('
                DROP TRIGGER IF EXISTS trg_check_escrow_balance_before_payroll_schedule_insert ON payroll_schedules;
                CREATE TRIGGER trg_check_escrow_balance_before_payroll_schedule_insert
                BEFORE INSERT ON payroll_schedules
                FOR EACH ROW
                EXECUTE FUNCTION check_escrow_balance_before_payroll_schedule();
            ');

            DB::unprepared('
                DROP TRIGGER IF EXISTS trg_check_escrow_balance_before_payroll_schedule_update ON payroll_schedules;
                CREATE TRIGGER trg_check_escrow_balance_before_payroll_schedule_update
                BEFORE UPDATE ON payroll_schedules
                FOR EACH ROW
                EXECUTE FUNCTION check_escrow_balance_before_payroll_schedule();
            ');
        }
        // PostgreSQL-only: triggers provide database-level enforcement
        // For other databases, rely on application-level validation
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $dbDriver = DB::getDriverName();

        if ($dbDriver === 'pgsql') {
            DB::unprepared('DROP TRIGGER IF EXISTS trg_check_escrow_balance_before_payment_schedule_insert ON payment_schedules;');
            DB::unprepared('DROP TRIGGER IF EXISTS trg_check_escrow_balance_before_payment_schedule_update ON payment_schedules;');
            DB::unprepared('DROP FUNCTION IF EXISTS check_escrow_balance_before_payment_schedule();');
            DB::unprepared('DROP TRIGGER IF EXISTS trg_check_escrow_balance_before_payroll_schedule_insert ON payroll_schedules;');
            DB::unprepared('DROP TRIGGER IF EXISTS trg_check_escrow_balance_before_payroll_schedule_update ON payroll_schedules;');
            DB::unprepared('DROP FUNCTION IF EXISTS check_escrow_balance_before_payroll_schedule();');
        }
    }
};
