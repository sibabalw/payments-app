<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Remove foreign key constraints from pivot tables to eliminate lock timeouts.
     * Referential integrity will be handled in application code via model observers.
     */
    public function up(): void
    {
        // Remove foreign keys from payment_schedule_recipient
        // MySQL auto-generates FK names, so we need to query them first
        $connection = Schema::getConnection();

        if (Schema::hasTable('payment_schedule_recipient')) {
            $fks = $connection->select("
                SELECT CONSTRAINT_NAME 
                FROM information_schema.KEY_COLUMN_USAGE 
                WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = 'payment_schedule_recipient' 
                AND REFERENCED_TABLE_NAME IS NOT NULL
            ");

            foreach ($fks as $fk) {
                $connection->statement("ALTER TABLE payment_schedule_recipient DROP FOREIGN KEY {$fk->CONSTRAINT_NAME}");
            }
        }

        // Remove foreign keys from payroll_schedule_employee
        if (Schema::hasTable('payroll_schedule_employee')) {
            $fks = $connection->select("
                SELECT CONSTRAINT_NAME 
                FROM information_schema.KEY_COLUMN_USAGE 
                WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = 'payroll_schedule_employee' 
                AND REFERENCED_TABLE_NAME IS NOT NULL
            ");

            foreach ($fks as $fk) {
                $connection->statement("ALTER TABLE payroll_schedule_employee DROP FOREIGN KEY {$fk->CONSTRAINT_NAME}");
            }
        }

        // Remove foreign keys from business_user
        if (Schema::hasTable('business_user')) {
            $fks = $connection->select("
                SELECT CONSTRAINT_NAME 
                FROM information_schema.KEY_COLUMN_USAGE 
                WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = 'business_user' 
                AND REFERENCED_TABLE_NAME IS NOT NULL
            ");

            foreach ($fks as $fk) {
                $connection->statement("ALTER TABLE business_user DROP FOREIGN KEY {$fk->CONSTRAINT_NAME}");
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Re-add foreign keys to payment_schedule_recipient
        Schema::table('payment_schedule_recipient', function (Blueprint $table) {
            $table->foreign('payment_schedule_id')
                ->references('id')
                ->on('payment_schedules')
                ->onDelete('cascade');
            $table->foreign('recipient_id')
                ->references('id')
                ->on('recipients')
                ->onDelete('cascade');
        });

        // Re-add foreign keys to payroll_schedule_employee
        Schema::table('payroll_schedule_employee', function (Blueprint $table) {
            $table->foreign('payroll_schedule_id')
                ->references('id')
                ->on('payroll_schedules')
                ->onDelete('cascade');
            $table->foreign('employee_id')
                ->references('id')
                ->on('employees')
                ->onDelete('cascade');
        });

        // Re-add foreign keys to business_user
        if (Schema::hasTable('business_user')) {
            Schema::table('business_user', function (Blueprint $table) {
                $table->foreign('business_id')
                    ->references('id')
                    ->on('businesses')
                    ->onDelete('cascade');
                $table->foreign('user_id')
                    ->references('id')
                    ->on('users')
                    ->onDelete('cascade');
            });
        }
    }
};
