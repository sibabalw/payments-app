<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('billing_transactions', function (Blueprint $table) {
            if (!Schema::hasColumn('billing_transactions', 'business_id')) {
                $table->foreignId('business_id')->after('id');
            }
            if (!Schema::hasColumn('billing_transactions', 'monthly_billing_id')) {
                $table->foreignId('monthly_billing_id')->nullable()->after('business_id');
            }
            if (!Schema::hasColumn('billing_transactions', 'type')) {
                $table->enum('type', ['deposit_fee', 'subscription_fee', 'refund'])->after('monthly_billing_id');
            }
            if (!Schema::hasColumn('billing_transactions', 'amount')) {
                $table->decimal('amount', 15, 2)->after('type');
            }
            if (!Schema::hasColumn('billing_transactions', 'currency')) {
                $table->string('currency', 3)->default('ZAR')->after('amount');
            }
            if (!Schema::hasColumn('billing_transactions', 'description')) {
                $table->text('description')->nullable()->after('currency');
            }
            if (!Schema::hasColumn('billing_transactions', 'status')) {
                $table->enum('status', ['pending', 'completed', 'failed'])->default('pending')->after('description');
            }
            if (!Schema::hasColumn('billing_transactions', 'bank_reference')) {
                $table->string('bank_reference')->nullable()->after('status');
            }
            if (!Schema::hasColumn('billing_transactions', 'processed_at')) {
                $table->timestamp('processed_at')->nullable()->after('bank_reference');
            }
        });

        // Add foreign keys and indexes separately with error handling
        if (Schema::hasColumn('billing_transactions', 'business_id')) {
            try {
                Schema::table('billing_transactions', function (Blueprint $table) {
                    $table->foreign('business_id')->references('id')->on('businesses')->onDelete('cascade');
                });
            } catch (\Exception $e) {
                // Foreign key might already exist, ignore
            }
        }

        if (Schema::hasColumn('billing_transactions', 'monthly_billing_id')) {
            try {
                Schema::table('billing_transactions', function (Blueprint $table) {
                    $table->foreign('monthly_billing_id')->references('id')->on('monthly_billings')->onDelete('set null');
                });
            } catch (\Exception $e) {
                // Foreign key might already exist, ignore
            }
        }

        // Add indexes
        try {
            Schema::table('billing_transactions', function (Blueprint $table) {
                if (Schema::hasColumn('billing_transactions', 'business_id') && Schema::hasColumn('billing_transactions', 'type')) {
                    $table->index(['business_id', 'type']);
                }
            });
        } catch (\Exception $e) {
            // Index might already exist, ignore
        }

        try {
            Schema::table('billing_transactions', function (Blueprint $table) {
                if (Schema::hasColumn('billing_transactions', 'monthly_billing_id')) {
                    $table->index('monthly_billing_id');
                }
            });
        } catch (\Exception $e) {
            // Index might already exist, ignore
        }

        try {
            Schema::table('billing_transactions', function (Blueprint $table) {
                if (Schema::hasColumn('billing_transactions', 'status')) {
                    $table->index('status');
                }
            });
        } catch (\Exception $e) {
            // Index might already exist, ignore
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('billing_transactions', function (Blueprint $table) {
            $table->dropForeign(['business_id']);
            $table->dropForeign(['monthly_billing_id']);
            $table->dropIndex(['business_id', 'type']);
            $table->dropIndex(['monthly_billing_id']);
            $table->dropIndex(['status']);
            $table->dropColumn([
                'business_id',
                'monthly_billing_id',
                'type',
                'amount',
                'currency',
                'description',
                'status',
                'bank_reference',
                'processed_at',
            ]);
        });
    }
};
