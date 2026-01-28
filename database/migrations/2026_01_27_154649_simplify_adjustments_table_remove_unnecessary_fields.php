<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Simplifies adjustments table by:
     * - Removing is_recurring (inferred from period_start/end being null)
     * - Removing payroll_schedule_id (auto-detected when needed)
     * - Renaming payroll_period_start/end to period_start/end for simplicity
     */
    public function up(): void
    {
        // Migrate data: Set period_start/end to null for recurring adjustments
        // This ensures recurring adjustments have null periods (inferred as recurring)
        DB::table('adjustments')
            ->where('is_recurring', true)
            ->update([
                'payroll_period_start' => null,
                'payroll_period_end' => null,
            ]);

        // Drop foreign key and column for payroll_schedule_id first
        // This will make the composite index invalid, which is fine
        if (Schema::hasColumn('adjustments', 'payroll_schedule_id')) {
            Schema::table('adjustments', function (Blueprint $table) {
                $table->dropForeign(['payroll_schedule_id']);
                $table->dropIndex(['payroll_schedule_id']);
                $table->dropColumn('payroll_schedule_id');
            });
        }

        // Now try to drop the composite index (it may have become invalid or may still be protected by FK)
        // If it fails, MySQL will handle it when we create new indexes
        try {
            DB::statement('ALTER TABLE adjustments DROP INDEX idx_employee_once_off_adjustment');
        } catch (\Exception $e) {
            // Index might still be protected by foreign key on employee_id, or already invalid
            // This is fine - we'll create new indexes below
        }

        // Rename period columns using raw SQL (more reliable than renameColumn)
        if (Schema::hasColumn('adjustments', 'payroll_period_start')) {
            DB::statement('ALTER TABLE adjustments CHANGE payroll_period_start period_start DATE NULL');
        }
        if (Schema::hasColumn('adjustments', 'payroll_period_end')) {
            DB::statement('ALTER TABLE adjustments CHANGE payroll_period_end period_end DATE NULL');
        }

        // Drop is_recurring column and old index
        Schema::table('adjustments', function (Blueprint $table) {
            if (Schema::hasColumn('adjustments', 'is_recurring')) {
                $table->dropColumn('is_recurring');
            }

            // Drop old index that included is_recurring
            try {
                DB::statement('ALTER TABLE adjustments DROP INDEX adjustments_business_id_is_recurring_index');
            } catch (\Exception $e) {
                // Try alternative index name format
                try {
                    $table->dropIndex(['business_id', 'is_recurring']);
                } catch (\Exception $e2) {
                    // Index might not exist or have different name, ignore
                }
            }
        });

        // Create new indexes for the simplified model
        Schema::table('adjustments', function (Blueprint $table) {
            // Index for querying by business and employee
            $table->index(['business_id', 'employee_id', 'is_active'], 'idx_business_employee_active');

            // Index for period-based queries (recurring = null period, one-off = set period)
            $table->index(['period_start', 'period_end'], 'idx_period_range');

            // Note: Recurring adjustments are those with period_start = null
            // This index helps query active recurring adjustments
            $table->index(['business_id', 'is_active', 'period_start'], 'idx_business_active_period');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('adjustments', function (Blueprint $table) {
            // Drop new indexes
            try {
                $table->dropIndex('idx_business_employee_active');
            } catch (\Exception $e) {
                // Ignore if doesn't exist
            }
            try {
                $table->dropIndex('idx_period_range');
            } catch (\Exception $e) {
                // Ignore if doesn't exist
            }
            try {
                $table->dropIndex('idx_business_active_period');
            } catch (\Exception $e) {
                // Ignore if doesn't exist
            }

            // Add back is_recurring column
            $table->boolean('is_recurring')->default(true)->after('adjustment_type');

            // Rename period columns back using raw SQL
            if (Schema::hasColumn('adjustments', 'period_start')) {
                DB::statement('ALTER TABLE adjustments CHANGE period_start payroll_period_start DATE NULL');
            }
            if (Schema::hasColumn('adjustments', 'period_end')) {
                DB::statement('ALTER TABLE adjustments CHANGE period_end payroll_period_end DATE NULL');
            }

            // Add back payroll_schedule_id
            $table->foreignId('payroll_schedule_id')
                ->nullable()
                ->after('employee_id')
                ->constrained()
                ->onDelete('cascade');
            $table->index('payroll_schedule_id');
        });

        // Migrate data back: Set is_recurring based on period_start/end
        DB::table('adjustments')
            ->whereNull('payroll_period_start')
            ->whereNull('payroll_period_end')
            ->update(['is_recurring' => true]);

        DB::table('adjustments')
            ->whereNotNull('payroll_period_start')
            ->orWhereNotNull('payroll_period_end')
            ->update(['is_recurring' => false]);

        // Recreate old index
        Schema::table('adjustments', function (Blueprint $table) {
            $table->index(['business_id', 'is_recurring']);
            $table->index(
                ['employee_id', 'payroll_schedule_id', 'payroll_period_start', 'payroll_period_end', 'is_recurring'],
                'idx_employee_once_off_adjustment'
            );
        });
    }
};
