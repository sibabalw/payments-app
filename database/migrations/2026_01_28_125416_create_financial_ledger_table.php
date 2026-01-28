<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Check if an index exists on a table.
     */
    private function hasIndex(string $table, string $indexName): bool
    {
        try {
            $indexes = Schema::getIndexes($table);

            foreach ($indexes as $index) {
                if ($index['name'] === $indexName) {
                    return true;
                }
            }
        } catch (\Exception $e) {
            return false;
        }

        return false;
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('financial_ledger')) {
            // Table already exists, just ensure indexes are correct
            Schema::table('financial_ledger', function (Blueprint $table) {
                // Check and add missing indexes if needed
                if (! $this->hasIndex('financial_ledger', 'financial_ledger_correlation_id_index')) {
                    $table->index(['correlation_id']);
                }
            });

            return;
        }

        Schema::create('financial_ledger', function (Blueprint $table) {
            $table->id();
            $table->string('correlation_id', 64); // For tracing related transactions
            $table->string('transaction_type', 32); // DEBIT or CREDIT
            $table->string('account_type', 32); // ESCROW, PAYROLL, PAYMENT, FEES, TAXES
            $table->foreignId('business_id')->constrained()->onDelete('cascade');
            $table->string('reference_type', 128)->nullable(); // Model class name (PayrollJob, PaymentJob, EscrowDeposit, etc.)
            $table->unsignedBigInteger('reference_id')->nullable(); // ID of the referenced model
            $table->decimal('amount', 15, 2); // Amount (always positive, type determines debit/credit)
            $table->string('currency', 3)->default('ZAR');
            $table->text('description')->nullable();
            $table->json('metadata')->nullable(); // Additional context (before/after balances, user info, etc.)
            $table->string('reversal_of_id')->nullable(); // ID of reversed ledger entry
            $table->foreignId('reversed_by_id')->nullable()->constrained('financial_ledger')->onDelete('set null');
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null'); // User who initiated the transaction
            $table->timestamp('effective_at')->useCurrent(); // When the transaction is effective
            $table->timestamps();

            // Indexes for fast reconciliation queries
            $table->index(['business_id', 'account_type', 'created_at']);
            $table->index(['correlation_id']);
            $table->index(['reference_type', 'reference_id']);
            $table->index(['effective_at']);
            $table->index(['transaction_type', 'account_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('financial_ledger');
    }
};
