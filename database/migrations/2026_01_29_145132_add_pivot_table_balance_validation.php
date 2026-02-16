<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds PostgreSQL triggers on pivot tables to validate escrow balance
     * when recipients/employees are attached to schedules.
     */
    public function up(): void
    {
        $dbDriver = DB::getDriverName();

        if ($dbDriver === 'pgsql') {
            // Function to check escrow balance before payment schedule recipient attachment
            // CRITICAL: This trigger prevents attaching recipients that would exceed available balance
            DB::unprepared("
                CREATE OR REPLACE FUNCTION check_escrow_balance_before_payment_schedule_recipient()
                RETURNS TRIGGER AS \$\$
                DECLARE
                    schedule_record RECORD;
                    business_record RECORD;
                    available_balance DECIMAL(15,2);
                    recipient_count INTEGER;
                    total_amount DECIMAL(15,2);
                BEGIN
                    -- Get schedule details with business info
                    SELECT ps.id, ps.business_id, ps.amount, b.escrow_balance, b.hold_amount
                    INTO schedule_record
                    FROM payment_schedules ps
                    JOIN businesses b ON b.id = ps.business_id
                    WHERE ps.id = NEW.payment_schedule_id
                    FOR UPDATE OF b;
                    
                    -- Check if schedule exists
                    IF schedule_record.id IS NULL THEN
                        RAISE EXCEPTION 'Cannot attach recipient: payment schedule not found';
                    END IF;
                    
                    -- Lock the business row to prevent concurrent modifications
                    SELECT b.escrow_balance, b.hold_amount, b.id
                    INTO business_record
                    FROM businesses b
                    WHERE b.id = schedule_record.business_id
                    FOR UPDATE OF b;
                    
                    -- CRITICAL CHECK #1: escrow_balance must be NOT NULL
                    IF business_record.escrow_balance IS NULL THEN
                        RAISE EXCEPTION 'Cannot attach recipient: escrow balance is NULL. Business ID: %. This is a critical error - active businesses must have a non-NULL escrow balance.', 
                            schedule_record.business_id;
                    END IF;
                    
                    -- CRITICAL CHECK #2: escrow_balance must be greater than zero (explicit zero check)
                    IF business_record.escrow_balance = 0 THEN
                        RAISE EXCEPTION 'Cannot attach recipient: escrow balance is zero. Business ID: %. No recipients can be attached when escrow balance is zero.', 
                            schedule_record.business_id;
                    END IF;
                    
                    -- CRITICAL CHECK #3: escrow_balance must not be negative
                    IF business_record.escrow_balance < 0 THEN
                        RAISE EXCEPTION 'Cannot attach recipient: escrow balance is negative (%). Business ID: %. Negative balances are not allowed.', 
                            business_record.escrow_balance, schedule_record.business_id;
                    END IF;
                    
                    -- Calculate available balance (escrow_balance - hold_amount)
                    available_balance := business_record.escrow_balance - COALESCE(business_record.hold_amount, 0);
                    
                    -- CRITICAL CHECK #4: available balance must be greater than zero
                    IF available_balance = 0 THEN
                        RAISE EXCEPTION 'Cannot attach recipient: available balance is zero (all funds on hold). Business ID: %. Available: %, Hold: %', 
                            schedule_record.business_id, available_balance, COALESCE(business_record.hold_amount, 0);
                    END IF;
                    
                    IF available_balance < 0 THEN
                        RAISE EXCEPTION 'Cannot attach recipient: available balance is negative (%). Business ID: %. Hold amount exceeds escrow balance.', 
                            available_balance, schedule_record.business_id;
                    END IF;
                    
                    -- Get current recipient count (including this new one being inserted)
                    SELECT COALESCE(COUNT(*), 0)
                    INTO recipient_count
                    FROM payment_schedule_recipient
                    WHERE payment_schedule_id = NEW.payment_schedule_id;
                    
                    -- Calculate total amount required (amount × recipient_count including new recipient)
                    total_amount := schedule_record.amount * recipient_count;
                    
                    -- CRITICAL CHECK #5: available balance must be sufficient for total amount
                    IF available_balance < total_amount THEN
                        RAISE EXCEPTION 'Cannot attach recipient: insufficient escrow balance. Available: %, Required: % (amount: % × recipients: %), Business ID: %', 
                            available_balance, total_amount, schedule_record.amount, recipient_count, schedule_record.business_id;
                    END IF;
                    
                    RETURN NEW;
                END;
                \$\$ LANGUAGE plpgsql;
            ");

            // Trigger for payment_schedule_recipient INSERT
            DB::unprepared('
                DROP TRIGGER IF EXISTS trg_check_escrow_balance_before_payment_schedule_recipient_insert ON payment_schedule_recipient;
                CREATE TRIGGER trg_check_escrow_balance_before_payment_schedule_recipient_insert
                BEFORE INSERT ON payment_schedule_recipient
                FOR EACH ROW
                EXECUTE FUNCTION check_escrow_balance_before_payment_schedule_recipient();
            ');

            // Function to check escrow balance before payroll schedule employee attachment
            // CRITICAL: This trigger prevents attaching employees that would exceed available balance
            DB::unprepared("
                CREATE OR REPLACE FUNCTION check_escrow_balance_before_payroll_schedule_employee()
                RETURNS TRIGGER AS \$\$
                DECLARE
                    schedule_record RECORD;
                    business_record RECORD;
                    available_balance DECIMAL(15,2);
                    total_estimate DECIMAL(15,2);
                    employee_gross_salary DECIMAL(15,2);
                BEGIN
                    -- Get schedule details with business info
                    SELECT ps.id, ps.business_id, b.escrow_balance, b.hold_amount
                    INTO schedule_record
                    FROM payroll_schedules ps
                    JOIN businesses b ON b.id = ps.business_id
                    WHERE ps.id = NEW.payroll_schedule_id
                    FOR UPDATE OF b;
                    
                    -- Check if schedule exists
                    IF schedule_record.id IS NULL THEN
                        RAISE EXCEPTION 'Cannot attach employee: payroll schedule not found';
                    END IF;
                    
                    -- Lock the business row to prevent concurrent modifications
                    SELECT b.escrow_balance, b.hold_amount, b.id
                    INTO business_record
                    FROM businesses b
                    WHERE b.id = schedule_record.business_id
                    FOR UPDATE OF b;
                    
                    -- CRITICAL CHECK #1: escrow_balance must be NOT NULL
                    IF business_record.escrow_balance IS NULL THEN
                        RAISE EXCEPTION 'Cannot attach employee: escrow balance is NULL. Business ID: %. This is a critical error - active businesses must have a non-NULL escrow balance.', 
                            schedule_record.business_id;
                    END IF;
                    
                    -- CRITICAL CHECK #2: escrow_balance must be greater than zero (explicit zero check)
                    IF business_record.escrow_balance = 0 THEN
                        RAISE EXCEPTION 'Cannot attach employee: escrow balance is zero. Business ID: %. No employees can be attached when escrow balance is zero.', 
                            schedule_record.business_id;
                    END IF;
                    
                    -- CRITICAL CHECK #3: escrow_balance must not be negative
                    IF business_record.escrow_balance < 0 THEN
                        RAISE EXCEPTION 'Cannot attach employee: escrow balance is negative (%). Business ID: %. Negative balances are not allowed.', 
                            business_record.escrow_balance, schedule_record.business_id;
                    END IF;
                    
                    -- Calculate available balance (escrow_balance - hold_amount)
                    available_balance := business_record.escrow_balance - COALESCE(business_record.hold_amount, 0);
                    
                    -- CRITICAL CHECK #4: available balance must be greater than zero
                    IF available_balance = 0 THEN
                        RAISE EXCEPTION 'Cannot attach employee: available balance is zero (all funds on hold). Business ID: %. Available: %, Hold: %', 
                            schedule_record.business_id, available_balance, COALESCE(business_record.hold_amount, 0);
                    END IF;
                    
                    IF available_balance < 0 THEN
                        RAISE EXCEPTION 'Cannot attach employee: available balance is negative (%). Business ID: %. Hold amount exceeds escrow balance.', 
                            available_balance, schedule_record.business_id;
                    END IF;
                    
                    -- Get gross salary for the employee being attached
                    SELECT COALESCE(gross_salary, 0)
                    INTO employee_gross_salary
                    FROM employees
                    WHERE id = NEW.employee_id;
                    
                    -- Calculate total estimate based on gross salaries of all employees in this schedule (including new one)
                    -- Use gross_salary as conservative estimate (net will be less after deductions)
                    SELECT COALESCE(SUM(e.gross_salary), 0) + employee_gross_salary
                    INTO total_estimate
                    FROM payroll_schedule_employee pse
                    JOIN employees e ON e.id = pse.employee_id
                    WHERE pse.payroll_schedule_id = NEW.payroll_schedule_id;
                    
                    -- CRITICAL CHECK #5: available balance must be sufficient for total estimate
                    IF available_balance < total_estimate THEN
                        RAISE EXCEPTION 'Cannot attach employee: insufficient escrow balance. Available: %, Estimated required: % (based on gross salaries including new employee), Business ID: %', 
                            available_balance, total_estimate, schedule_record.business_id;
                    END IF;
                    
                    RETURN NEW;
                END;
                \$\$ LANGUAGE plpgsql;
            ");

            // Trigger for payroll_schedule_employee INSERT
            DB::unprepared('
                DROP TRIGGER IF EXISTS trg_check_escrow_balance_before_payroll_schedule_employee_insert ON payroll_schedule_employee;
                CREATE TRIGGER trg_check_escrow_balance_before_payroll_schedule_employee_insert
                BEFORE INSERT ON payroll_schedule_employee
                FOR EACH ROW
                EXECUTE FUNCTION check_escrow_balance_before_payroll_schedule_employee();
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
            DB::unprepared('DROP TRIGGER IF EXISTS trg_check_escrow_balance_before_payment_schedule_recipient_insert ON payment_schedule_recipient;');
            DB::unprepared('DROP FUNCTION IF EXISTS check_escrow_balance_before_payment_schedule_recipient();');
            DB::unprepared('DROP TRIGGER IF EXISTS trg_check_escrow_balance_before_payroll_schedule_employee_insert ON payroll_schedule_employee;');
            DB::unprepared('DROP FUNCTION IF EXISTS check_escrow_balance_before_payroll_schedule_employee();');
        }
    }
};
