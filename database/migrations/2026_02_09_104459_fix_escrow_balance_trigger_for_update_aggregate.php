<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * PostgreSQL does not allow FOR UPDATE with aggregate functions (SUM).
     * Replace the escrow balance trigger functions to remove FOR UPDATE from
     * the aggregate SELECT; the business row lock (FOR UPDATE OF b) already
     * serializes balance checks.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared("
            CREATE OR REPLACE FUNCTION check_escrow_balance_before_payment_job()
            RETURNS TRIGGER AS \$\$
            DECLARE
                business_record RECORD;
                available_balance DECIMAL(15,2);
                total_pending_amount DECIMAL(15,2);
            BEGIN
                IF NEW.amount IS NULL OR NEW.amount <= 0 THEN
                    RAISE EXCEPTION 'Cannot create payment job: job amount must be positive. Amount: %',
                        COALESCE(NEW.amount::text, 'NULL');
                END IF;

                SELECT b.escrow_balance, b.hold_amount, ps.business_id
                INTO business_record
                FROM payment_schedules ps
                JOIN businesses b ON b.id = ps.business_id
                WHERE ps.id = NEW.payment_schedule_id
                FOR UPDATE OF b;

                IF business_record.business_id IS NULL THEN
                    RAISE EXCEPTION 'Cannot create payment job: payment schedule not found';
                END IF;

                IF business_record.escrow_balance IS NULL THEN
                    RAISE EXCEPTION 'Cannot create payment job: escrow balance is NULL. Business ID: %.',
                        business_record.business_id;
                END IF;

                IF business_record.escrow_balance = 0 THEN
                    RAISE EXCEPTION 'Cannot create payment job: escrow balance is zero. Business ID: %.',
                        business_record.business_id;
                END IF;

                IF business_record.escrow_balance < 0 THEN
                    RAISE EXCEPTION 'Cannot create payment job: escrow balance is negative (%). Business ID: %.',
                        business_record.escrow_balance, business_record.business_id;
                END IF;

                available_balance := business_record.escrow_balance - COALESCE(business_record.hold_amount, 0);

                IF available_balance = 0 THEN
                    RAISE EXCEPTION 'Cannot create payment job: available balance is zero. Business ID: %.',
                        business_record.business_id;
                END IF;

                IF available_balance < 0 THEN
                    RAISE EXCEPTION 'Cannot create payment job: available balance is negative (%). Business ID: %.',
                        available_balance, business_record.business_id;
                END IF;

                IF available_balance < NEW.amount THEN
                    RAISE EXCEPTION 'Cannot create payment job: insufficient escrow balance. Available: %, Required: %, Business ID: %',
                        available_balance, NEW.amount, business_record.business_id;
                END IF;

                SELECT COALESCE(SUM(pj.amount), 0) + NEW.amount
                INTO total_pending_amount
                FROM payment_jobs pj
                JOIN payment_schedules ps2 ON ps2.id = pj.payment_schedule_id
                WHERE ps2.business_id = business_record.business_id
                AND pj.status = 'pending';

                IF available_balance < total_pending_amount THEN
                    RAISE EXCEPTION 'Cannot create payment job: total pending jobs would exceed available balance. Available: %, Total pending: %, Business ID: %',
                        available_balance, total_pending_amount, business_record.business_id;
                END IF;

                RETURN NEW;
            END;
            \$\$ LANGUAGE plpgsql;
        ");

        DB::unprepared("
            CREATE OR REPLACE FUNCTION check_escrow_balance_before_payroll_job()
            RETURNS TRIGGER AS \$\$
            DECLARE
                business_record RECORD;
                available_balance DECIMAL(15,2);
                total_pending_amount DECIMAL(15,2);
            BEGIN
                IF NEW.net_salary IS NULL OR NEW.net_salary <= 0 THEN
                    RAISE EXCEPTION 'Cannot create payroll job: net_salary must be positive. Net salary: %',
                        COALESCE(NEW.net_salary::text, 'NULL');
                END IF;

                SELECT b.escrow_balance, b.hold_amount, ps.business_id
                INTO business_record
                FROM payroll_schedules ps
                JOIN businesses b ON b.id = ps.business_id
                WHERE ps.id = NEW.payroll_schedule_id
                FOR UPDATE OF b;

                IF business_record.business_id IS NULL THEN
                    RAISE EXCEPTION 'Cannot create payroll job: payroll schedule not found';
                END IF;

                IF business_record.escrow_balance IS NULL THEN
                    RAISE EXCEPTION 'Cannot create payroll job: escrow balance is NULL. Business ID: %.',
                        business_record.business_id;
                END IF;

                IF business_record.escrow_balance = 0 THEN
                    RAISE EXCEPTION 'Cannot create payroll job: escrow balance is zero. Business ID: %.',
                        business_record.business_id;
                END IF;

                IF business_record.escrow_balance < 0 THEN
                    RAISE EXCEPTION 'Cannot create payroll job: escrow balance is negative (%). Business ID: %.',
                        business_record.escrow_balance, business_record.business_id;
                END IF;

                available_balance := business_record.escrow_balance - COALESCE(business_record.hold_amount, 0);

                IF available_balance = 0 THEN
                    RAISE EXCEPTION 'Cannot create payroll job: available balance is zero. Business ID: %.',
                        business_record.business_id;
                END IF;

                IF available_balance < 0 THEN
                    RAISE EXCEPTION 'Cannot create payroll job: available balance is negative (%). Business ID: %.',
                        available_balance, business_record.business_id;
                END IF;

                IF available_balance < NEW.net_salary THEN
                    RAISE EXCEPTION 'Cannot create payroll job: insufficient escrow balance. Available: %, Required: %, Business ID: %',
                        available_balance, NEW.net_salary, business_record.business_id;
                END IF;

                SELECT COALESCE(SUM(pj.net_salary), 0) + NEW.net_salary
                INTO total_pending_amount
                FROM payroll_jobs pj
                JOIN payroll_schedules ps2 ON ps2.id = pj.payroll_schedule_id
                WHERE ps2.business_id = business_record.business_id
                AND pj.status = 'pending';

                IF available_balance < total_pending_amount THEN
                    RAISE EXCEPTION 'Cannot create payroll job: total pending jobs would exceed available balance. Available: %, Total pending: %, Business ID: %',
                        available_balance, total_pending_amount, business_record.business_id;
                END IF;

                RETURN NEW;
            END;
            \$\$ LANGUAGE plpgsql;
        ");
    }

    /**
     * Reverse the migrations.
     *
     * Down would require re-applying the old function bodies; we leave
     * the fixed version in place (no-op).
     */
    public function down(): void
    {
        // Intentionally no-op: keeping the fixed functions in place
    }
};
