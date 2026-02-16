<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds PostgreSQL triggers to prevent payment and payroll job creation
     * when escrow balance is zero or insufficient.
     */
    public function up(): void
    {
        $dbDriver = DB::getDriverName();

        if ($dbDriver === 'pgsql') {
            // Function to check escrow balance before payment job insertion
            // CRITICAL: This trigger is the final database-level defense against creating jobs with insufficient balance.
            // This trigger makes it IMPOSSIBLE to create payment jobs when escrow balance is zero or insufficient.
            // It prevents job creation at the database level, even if application-level checks are bypassed.
            DB::unprepared("
                CREATE OR REPLACE FUNCTION check_escrow_balance_before_payment_job()
                RETURNS TRIGGER AS \$\$
                DECLARE
                    business_record RECORD;
                    available_balance DECIMAL(15,2);
                    total_pending_amount DECIMAL(15,2);
                BEGIN
                    -- CRITICAL: Validate job amount is positive (first line of defense)
                    -- This prevents creating jobs with zero or negative amounts
                    IF NEW.amount IS NULL OR NEW.amount <= 0 THEN
                        RAISE EXCEPTION 'Cannot create payment job: job amount must be positive. Amount: %', 
                            COALESCE(NEW.amount::text, 'NULL');
                    END IF;
                    
                    -- Lock the business row to prevent concurrent modifications during check
                    -- Get business and calculate available balance with row lock
                    -- FOR UPDATE OF b ensures serialization of balance checks across concurrent transactions
                    SELECT b.escrow_balance, b.hold_amount, ps.business_id
                    INTO business_record
                    FROM payment_schedules ps
                    JOIN businesses b ON b.id = ps.business_id
                    WHERE ps.id = NEW.payment_schedule_id
                    FOR UPDATE OF b;
                    
                    -- Check if business exists
                    IF business_record.business_id IS NULL THEN
                        RAISE EXCEPTION 'Cannot create payment job: payment schedule not found';
                    END IF;
                    
                    -- CRITICAL CHECK #1: escrow_balance must be NOT NULL
                    -- This is the first critical check - prevents creating jobs when balance is NULL
                    IF business_record.escrow_balance IS NULL THEN
                        RAISE EXCEPTION 'Cannot create payment job: escrow balance is NULL. Business ID: %. This is a critical error - active businesses must have a non-NULL escrow balance.', 
                            business_record.business_id;
                    END IF;
                    
                    -- CRITICAL CHECK #2: escrow_balance must be greater than zero (explicit zero check)
                    -- This is the SECOND critical check - prevents creating jobs when balance is exactly zero
                    -- This check is prioritized to catch zero balance cases immediately
                    IF business_record.escrow_balance = 0 THEN
                        RAISE EXCEPTION 'Cannot create payment job: escrow balance is zero. Business ID: %. No payment jobs can be created when escrow balance is zero.', 
                            business_record.business_id;
                    END IF;
                    
                    -- CRITICAL CHECK #3: escrow_balance must not be negative
                    IF business_record.escrow_balance < 0 THEN
                        RAISE EXCEPTION 'Cannot create payment job: escrow balance is negative (%). Business ID: %. Negative balances are not allowed.', 
                            business_record.escrow_balance, business_record.business_id;
                    END IF;
                    
                    -- Calculate available balance (escrow_balance - hold_amount)
                    -- hold_amount defaults to 0 if NULL
                    available_balance := business_record.escrow_balance - COALESCE(business_record.hold_amount, 0);
                    
                    -- CRITICAL CHECK #4: available balance must be greater than zero
                    -- This prevents creating jobs when all balance is on hold
                    IF available_balance = 0 THEN
                        RAISE EXCEPTION 'Cannot create payment job: available balance is zero (all funds on hold). Business ID: %. Available: %, Hold: %', 
                            business_record.business_id, available_balance, COALESCE(business_record.hold_amount, 0);
                    END IF;
                    
                    IF available_balance < 0 THEN
                        RAISE EXCEPTION 'Cannot create payment job: available balance is negative (%). Business ID: %. Hold amount exceeds escrow balance.', 
                            available_balance, business_record.business_id;
                    END IF;
                    
                    -- CRITICAL CHECK #5: available balance must be sufficient for this job amount
                    -- This check must come BEFORE total pending jobs check to catch individual job issues first
                    IF available_balance < NEW.amount THEN
                        RAISE EXCEPTION 'Cannot create payment job: insufficient escrow balance. Available: %, Required: %, Shortfall: %, Business ID: %', 
                            available_balance, NEW.amount, (NEW.amount - available_balance), business_record.business_id;
                    END IF;
                    
                    -- Calculate total pending jobs amount for this business (including this new job)
                    -- This prevents bulk inserts from exceeding balance when each row passes individually
                    -- The sum includes NEW.amount to account for jobs being inserted in the same transaction
                    -- Note: FOR UPDATE cannot be used with aggregate (SUM); business row lock above serializes checks
                    SELECT COALESCE(SUM(pj.amount), 0) + NEW.amount
                    INTO total_pending_amount
                    FROM payment_jobs pj
                    JOIN payment_schedules ps2 ON ps2.id = pj.payment_schedule_id
                    WHERE ps2.business_id = business_record.business_id
                    AND pj.status = 'pending';
                    
                    -- CRITICAL CHECK #6: available balance must be sufficient for total pending jobs (including this one)
                    -- This prevents creating multiple jobs that together exceed available balance
                    -- This is the final check to ensure bulk inserts don't exceed available balance
                    IF available_balance < total_pending_amount THEN
                        RAISE EXCEPTION 'Cannot create payment job: total pending jobs would exceed available balance. Available: %, Total pending (including this): %, Shortfall: %, Business ID: %', 
                            available_balance, total_pending_amount, (total_pending_amount - available_balance), business_record.business_id;
                    END IF;
                    
                    RETURN NEW;
                END;
                \$\$ LANGUAGE plpgsql;
            ");

            // Trigger for payment_jobs
            DB::unprepared('
                DROP TRIGGER IF EXISTS trg_check_escrow_balance_before_payment_job ON payment_jobs;
                CREATE TRIGGER trg_check_escrow_balance_before_payment_job
                BEFORE INSERT ON payment_jobs
                FOR EACH ROW
                EXECUTE FUNCTION check_escrow_balance_before_payment_job();
            ');

            // Function to check escrow balance before payroll job insertion
            // CRITICAL: This trigger is the final database-level defense against creating jobs with insufficient balance.
            // This trigger makes it IMPOSSIBLE to create payroll jobs when escrow balance is zero or insufficient.
            // It prevents job creation at the database level, even if application-level checks are bypassed.
            DB::unprepared("
                CREATE OR REPLACE FUNCTION check_escrow_balance_before_payroll_job()
                RETURNS TRIGGER AS \$\$
                DECLARE
                    business_record RECORD;
                    available_balance DECIMAL(15,2);
                    total_pending_amount DECIMAL(15,2);
                BEGIN
                    -- CRITICAL: Validate net_salary is positive (first line of defense)
                    -- This prevents creating jobs with zero or negative net_salary
                    -- net_salary is what's actually paid out to employees
                    IF NEW.net_salary IS NULL OR NEW.net_salary <= 0 THEN
                        RAISE EXCEPTION 'Cannot create payroll job: net_salary must be positive. Net salary: %', 
                            COALESCE(NEW.net_salary::text, 'NULL');
                    END IF;
                    
                    -- Lock the business row to prevent concurrent modifications during check
                    -- Get business and calculate available balance with row lock
                    -- FOR UPDATE OF b ensures serialization of balance checks across concurrent transactions
                    SELECT b.escrow_balance, b.hold_amount, ps.business_id
                    INTO business_record
                    FROM payroll_schedules ps
                    JOIN businesses b ON b.id = ps.business_id
                    WHERE ps.id = NEW.payroll_schedule_id
                    FOR UPDATE OF b;
                    
                    -- Check if business exists
                    IF business_record.business_id IS NULL THEN
                        RAISE EXCEPTION 'Cannot create payroll job: payroll schedule not found';
                    END IF;
                    
                    -- CRITICAL CHECK #1: escrow_balance must be NOT NULL
                    -- This is the first critical check - prevents creating jobs when balance is NULL
                    IF business_record.escrow_balance IS NULL THEN
                        RAISE EXCEPTION 'Cannot create payroll job: escrow balance is NULL. Business ID: %. This is a critical error - active businesses must have a non-NULL escrow balance.', 
                            business_record.business_id;
                    END IF;
                    
                    -- CRITICAL CHECK #2: escrow_balance must be greater than zero (explicit zero check)
                    -- This is the SECOND critical check - prevents creating jobs when balance is exactly zero
                    -- This check is prioritized to catch zero balance cases immediately
                    IF business_record.escrow_balance = 0 THEN
                        RAISE EXCEPTION 'Cannot create payroll job: escrow balance is zero. Business ID: %. No payroll jobs can be created when escrow balance is zero.', 
                            business_record.business_id;
                    END IF;
                    
                    -- CRITICAL CHECK #3: escrow_balance must not be negative
                    IF business_record.escrow_balance < 0 THEN
                        RAISE EXCEPTION 'Cannot create payroll job: escrow balance is negative (%). Business ID: %. Negative balances are not allowed.', 
                            business_record.escrow_balance, business_record.business_id;
                    END IF;
                    
                    -- Calculate available balance (escrow_balance - hold_amount)
                    -- hold_amount defaults to 0 if NULL
                    available_balance := business_record.escrow_balance - COALESCE(business_record.hold_amount, 0);
                    
                    -- CRITICAL CHECK #4: available balance must be greater than zero
                    -- This prevents creating jobs when all balance is on hold
                    IF available_balance = 0 THEN
                        RAISE EXCEPTION 'Cannot create payroll job: available balance is zero (all funds on hold). Business ID: %. Available: %, Hold: %', 
                            business_record.business_id, available_balance, COALESCE(business_record.hold_amount, 0);
                    END IF;
                    
                    IF available_balance < 0 THEN
                        RAISE EXCEPTION 'Cannot create payroll job: available balance is negative (%). Business ID: %. Hold amount exceeds escrow balance.', 
                            available_balance, business_record.business_id;
                    END IF;
                    
                    -- CRITICAL CHECK #5: available balance must be sufficient for this job (use net_salary as that's what's actually paid)
                    -- This check must come BEFORE total pending jobs check to catch individual job issues first
                    IF available_balance < NEW.net_salary THEN
                        RAISE EXCEPTION 'Cannot create payroll job: insufficient escrow balance. Available: %, Required: %, Shortfall: %, Business ID: %', 
                            available_balance, NEW.net_salary, (NEW.net_salary - available_balance), business_record.business_id;
                    END IF;
                    
                    -- Calculate total pending jobs amount for this business (including this new job)
                    -- This prevents bulk inserts from exceeding balance when each row passes individually
                    -- Use net_salary as that's what's actually paid out to employees
                    -- The sum includes NEW.net_salary to account for jobs being inserted in the same transaction
                    -- Note: FOR UPDATE cannot be used with aggregate (SUM); business row lock above serializes checks
                    SELECT COALESCE(SUM(pj.net_salary), 0) + NEW.net_salary
                    INTO total_pending_amount
                    FROM payroll_jobs pj
                    JOIN payroll_schedules ps2 ON ps2.id = pj.payroll_schedule_id
                    WHERE ps2.business_id = business_record.business_id
                    AND pj.status = 'pending';
                    
                    -- CRITICAL CHECK #6: available balance must be sufficient for total pending jobs (including this one)
                    -- This prevents creating multiple jobs that together exceed available balance
                    -- This is the final check to ensure bulk inserts don't exceed available balance
                    IF available_balance < total_pending_amount THEN
                        RAISE EXCEPTION 'Cannot create payroll job: total pending jobs would exceed available balance. Available: %, Total pending (including this): %, Shortfall: %, Business ID: %', 
                            available_balance, total_pending_amount, (total_pending_amount - available_balance), business_record.business_id;
                    END IF;
                    
                    RETURN NEW;
                END;
                \$\$ LANGUAGE plpgsql;
            ");

            // Trigger for payroll_jobs
            DB::unprepared('
                DROP TRIGGER IF EXISTS trg_check_escrow_balance_before_payroll_job ON payroll_jobs;
                CREATE TRIGGER trg_check_escrow_balance_before_payroll_job
                BEFORE INSERT ON payroll_jobs
                FOR EACH ROW
                EXECUTE FUNCTION check_escrow_balance_before_payroll_job();
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
            DB::unprepared('DROP TRIGGER IF EXISTS trg_check_escrow_balance_before_payment_job ON payment_jobs;');
            DB::unprepared('DROP FUNCTION IF EXISTS check_escrow_balance_before_payment_job();');
            DB::unprepared('DROP TRIGGER IF EXISTS trg_check_escrow_balance_before_payroll_job ON payroll_jobs;');
            DB::unprepared('DROP FUNCTION IF EXISTS check_escrow_balance_before_payroll_job();');
        }
    }
};
