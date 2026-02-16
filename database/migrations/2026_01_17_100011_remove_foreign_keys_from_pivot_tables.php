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
        $connection = Schema::getConnection();
        $driver = $connection->getDriverName();

        if (Schema::hasTable('payment_schedule_recipient')) {
            $constraints = $this->getForeignKeys($connection, $driver, 'payment_schedule_recipient');
            foreach ($constraints as $name) {
                $this->dropForeignKey($connection, $driver, 'payment_schedule_recipient', $name);
            }
        }

        if (Schema::hasTable('payroll_schedule_employee')) {
            $constraints = $this->getForeignKeys($connection, $driver, 'payroll_schedule_employee');
            foreach ($constraints as $name) {
                $this->dropForeignKey($connection, $driver, 'payroll_schedule_employee', $name);
            }
        }

        if (Schema::hasTable('business_user')) {
            $constraints = $this->getForeignKeys($connection, $driver, 'business_user');
            foreach ($constraints as $name) {
                $this->dropForeignKey($connection, $driver, 'business_user', $name);
            }
        }
    }

    /**
     * Get foreign key constraint names for a table.
     *
     * @return array<int, string>
     */
    private function getForeignKeys($connection, string $driver, string $table): array
    {
        if ($driver === 'pgsql') {
            $rows = $connection->select("
                SELECT tc.constraint_name
                FROM information_schema.table_constraints tc
                WHERE tc.table_schema = 'public'
                AND tc.table_name = ?
                AND tc.constraint_type = 'FOREIGN KEY'
            ", [$table]);

            return array_map(fn ($row) => $row->constraint_name, $rows);
        }

        $rows = $connection->select('
            SELECT CONSTRAINT_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = ?
            AND REFERENCED_TABLE_NAME IS NOT NULL
        ', [$table]);

        $names = [];
        foreach ($rows as $row) {
            $names[$row->CONSTRAINT_NAME] = true;
        }

        return array_keys($names);
    }

    private function dropForeignKey($connection, string $driver, string $table, string $constraintName): void
    {
        if ($driver === 'pgsql') {
            $connection->statement("ALTER TABLE {$table} DROP CONSTRAINT \"{$constraintName}\"");
        } else {
            $connection->statement("ALTER TABLE {$table} DROP FOREIGN KEY {$constraintName}");
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
