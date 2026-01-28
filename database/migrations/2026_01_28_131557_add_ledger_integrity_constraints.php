<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('financial_ledger')) {
            return;
        }

        // Add sequence_number if it doesn't exist (needed for unique constraint)
        if (! Schema::hasColumn('financial_ledger', 'sequence_number')) {
            Schema::table('financial_ledger', function (Blueprint $table) {
                $table->unsignedBigInteger('sequence_number')->nullable()->after('id');
            });
        }

        // Add integrity constraints
        Schema::table('financial_ledger', function (Blueprint $table) {
            // Note: MySQL doesn't support CHECK constraints in older versions
            // We'll use raw SQL for maximum compatibility
        });

        // Add CHECK constraints using raw SQL (MySQL 8.0.16+)
        try {
            // Amount must be positive
            DB::statement('ALTER TABLE financial_ledger ADD CONSTRAINT chk_amount_positive CHECK (amount > 0)');
        } catch (\Exception $e) {
            // Ignore if constraint already exists or MySQL version doesn't support it
            // Application-level validation will handle this
        }

        try {
            // Transaction type must be DEBIT or CREDIT
            DB::statement("ALTER TABLE financial_ledger ADD CONSTRAINT chk_transaction_type CHECK (transaction_type IN ('DEBIT', 'CREDIT'))");
        } catch (\Exception $e) {
            // Ignore if constraint already exists
        }

        // Add unique constraint for correlation_id + sequence_number (if sequence_number exists)
        if (Schema::hasColumn('financial_ledger', 'sequence_number')) {
            try {
                Schema::table('financial_ledger', function (Blueprint $table) {
                    $table->unique(['correlation_id', 'sequence_number'], 'uk_correlation_sequence');
                });
            } catch (\Exception $e) {
                // Ignore if constraint already exists
            }
        }

        // Ensure reversal foreign key exists and is properly constrained
        if (Schema::hasColumn('financial_ledger', 'reversal_of_id')) {
            try {
                // Check if foreign key already exists
                $foreignKeys = DB::select("
                    SELECT CONSTRAINT_NAME 
                    FROM information_schema.KEY_COLUMN_USAGE 
                    WHERE TABLE_SCHEMA = DATABASE() 
                    AND TABLE_NAME = 'financial_ledger' 
                    AND COLUMN_NAME = 'reversal_of_id'
                    AND REFERENCED_TABLE_NAME IS NOT NULL
                ");

                if (empty($foreignKeys)) {
                    DB::statement('ALTER TABLE financial_ledger ADD CONSTRAINT fk_reversal_valid FOREIGN KEY (reversal_of_id) REFERENCES financial_ledger(id) ON DELETE RESTRICT');
                }
            } catch (\Exception $e) {
                // Ignore if constraint already exists
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('financial_ledger')) {
            return;
        }

        try {
            DB::statement('ALTER TABLE financial_ledger DROP CONSTRAINT IF EXISTS chk_amount_positive');
            DB::statement('ALTER TABLE financial_ledger DROP CONSTRAINT IF EXISTS chk_transaction_type');
        } catch (\Exception $e) {
            // Ignore errors
        }

        try {
            Schema::table('financial_ledger', function (Blueprint $table) {
                $table->dropUnique('uk_correlation_sequence');
            });
        } catch (\Exception $e) {
            // Ignore if doesn't exist
        }

        try {
            DB::statement('ALTER TABLE financial_ledger DROP FOREIGN KEY IF EXISTS fk_reversal_valid');
        } catch (\Exception $e) {
            // Ignore if doesn't exist
        }
    }
};
