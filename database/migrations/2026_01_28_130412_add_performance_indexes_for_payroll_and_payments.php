<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Performance indexes for payroll_jobs
        if (Schema::hasTable('payroll_jobs')) {
            Schema::table('payroll_jobs', function (Blueprint $table) {
                if (! $this->hasIndex('payroll_jobs', 'payroll_jobs_status_created_at_index')) {
                    $table->index(['status', 'created_at']);
                }
                // Check for both possible index names (auto-generated vs explicit)
                $indexExists = $this->hasIndex('payroll_jobs', 'payroll_jobs_schedule_status_index') ||
                               $this->hasIndex('payroll_jobs', 'payroll_jobs_payroll_schedule_id_status_index');
                if (! $indexExists) {
                    $table->index(['payroll_schedule_id', 'status']);
                }
                // Check for both possible index names (auto-generated vs explicit)
                $employeePeriodIndexExists = $this->hasIndex('payroll_jobs', 'payroll_jobs_employee_period_index') ||
                                            $this->hasIndex('payroll_jobs', 'payroll_jobs_employee_id_pay_period_start_pay_period_end_index');
                if (! $employeePeriodIndexExists) {
                    $table->index(['employee_id', 'pay_period_start', 'pay_period_end']);
                }
                if (Schema::hasColumn('payroll_jobs', 'settlement_window_id')) {
                    if (! $this->hasIndex('payroll_jobs', 'payroll_jobs_settlement_window_index')) {
                        $table->index('settlement_window_id');
                    }
                }
            });
        }

        // Performance indexes for payment_jobs
        if (Schema::hasTable('payment_jobs')) {
            Schema::table('payment_jobs', function (Blueprint $table) {
                if (! $this->hasIndex('payment_jobs', 'payment_jobs_status_created_at_index')) {
                    $table->index(['status', 'created_at']);
                }
                // Check for both possible index names (auto-generated vs explicit)
                $paymentScheduleStatusIndexExists = $this->hasIndex('payment_jobs', 'payment_jobs_schedule_status_index') ||
                                                    $this->hasIndex('payment_jobs', 'payment_jobs_payment_schedule_id_status_index');
                if (! $paymentScheduleStatusIndexExists) {
                    $table->index(['payment_schedule_id', 'status']);
                }
                if (Schema::hasColumn('payment_jobs', 'settlement_window_id')) {
                    if (! $this->hasIndex('payment_jobs', 'payment_jobs_settlement_window_index')) {
                        $table->index('settlement_window_id');
                    }
                }
            });
        }

        // Performance indexes for escrow_deposits
        if (Schema::hasTable('escrow_deposits')) {
            Schema::table('escrow_deposits', function (Blueprint $table) {
                // Check for both possible index names (auto-generated vs explicit)
                $businessStatusIndexExists = $this->hasIndex('escrow_deposits', 'escrow_deposits_business_status_index') ||
                                            $this->hasIndex('escrow_deposits', 'escrow_deposits_business_id_status_index');
                if (! $businessStatusIndexExists) {
                    $table->index(['business_id', 'status']);
                }
                if (! $this->hasIndex('escrow_deposits', 'escrow_deposits_status_deposited_at_index')) {
                    $table->index(['status', 'deposited_at']);
                }
            });
        }
    }

    public function down(): void
    {
        // Drop indexes if they exist
        if (Schema::hasTable('payroll_jobs')) {
            Schema::table('payroll_jobs', function (Blueprint $table) {
                $table->dropIndex('payroll_jobs_status_created_at_index');
                $table->dropIndex('payroll_jobs_schedule_status_index');
                $table->dropIndex('payroll_jobs_employee_period_index');
                if (Schema::hasColumn('payroll_jobs', 'settlement_window_id')) {
                    $table->dropIndex('payroll_jobs_settlement_window_index');
                }
            });
        }

        if (Schema::hasTable('payment_jobs')) {
            Schema::table('payment_jobs', function (Blueprint $table) {
                $table->dropIndex('payment_jobs_status_created_at_index');
                $table->dropIndex('payment_jobs_schedule_status_index');
                if (Schema::hasColumn('payment_jobs', 'settlement_window_id')) {
                    $table->dropIndex('payment_jobs_settlement_window_index');
                }
            });
        }

        if (Schema::hasTable('escrow_deposits')) {
            Schema::table('escrow_deposits', function (Blueprint $table) {
                $table->dropIndex('escrow_deposits_business_status_index');
                $table->dropIndex('escrow_deposits_status_deposited_at_index');
            });
        }
    }

    protected function hasIndex(string $table, string $indexName): bool
    {
        $connection = Schema::getConnection();
        $driver = $connection->getDriverName();

        if ($driver === 'pgsql') {
            $result = $connection->selectOne(
                "SELECT 1 FROM pg_indexes WHERE schemaname = 'public' AND tablename = ? AND indexname = ?",
                [$table, $indexName]
            );

            return $result !== null;
        }

        try {
            $databaseName = $connection->getDatabaseName();
            $indexes = $connection->select(
                'SELECT COUNT(*) as count FROM information_schema.statistics
                 WHERE table_schema = ? AND table_name = ? AND index_name = ?',
                [$databaseName, $table, $indexName]
            );

            return ! empty($indexes) && $indexes[0]->count > 0;
        } catch (\Exception $e) {
            try {
                $indexes = $connection->select("SHOW INDEX FROM {$table} WHERE Key_name = ?", [$indexName]);

                return ! empty($indexes);
            } catch (\Exception $e2) {
                return false;
            }
        }
    }
};
