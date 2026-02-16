<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Drop executed_payments and executed_payroll tables.
     * We now use status changes instead of moving records.
     */
    public function up(): void
    {
        Schema::dropIfExists('executed_payments');
        Schema::dropIfExists('executed_payroll');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Recreate executed_payments table
        Schema::create('executed_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_schedule_id')->constrained()->onDelete('cascade');
            $table->foreignId('recipient_id')->constrained()->onDelete('cascade');
            $table->decimal('amount', 15, 2);
            $table->string('currency', 3)->default('ZAR');
            $table->enum('status', ['succeeded', 'failed'])->default('succeeded');
            $table->text('error_message')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->string('transaction_id')->nullable();
            $table->decimal('fee', 15, 2)->nullable();
            $table->foreignId('escrow_deposit_id')->nullable()->constrained()->onDelete('set null');
            $table->timestamps();

            $table->index(['payment_schedule_id', 'status']);
            $table->index(['recipient_id', 'status']);
        });

        // Recreate executed_payroll table
        Schema::create('executed_payroll', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_schedule_id')->constrained()->onDelete('cascade');
            $table->foreignId('employee_id')->constrained()->onDelete('cascade');
            $table->decimal('gross_salary', 15, 2);
            $table->decimal('paye_amount', 15, 2)->default(0);
            $table->decimal('uif_amount', 15, 2)->default(0);
            $table->decimal('sdl_amount', 15, 2)->default(0);
            $table->decimal('net_salary', 15, 2);
            $table->string('currency', 3)->default('ZAR');
            $table->enum('status', ['succeeded', 'failed'])->default('succeeded');
            $table->text('error_message')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->string('transaction_id')->nullable();
            $table->decimal('fee', 15, 2)->nullable();
            $table->foreignId('escrow_deposit_id')->nullable()->constrained()->onDelete('set null');
            $table->date('pay_period_start')->nullable();
            $table->date('pay_period_end')->nullable();
            $table->timestamps();

            $table->index(['payroll_schedule_id', 'status']);
            $table->index(['employee_id', 'status']);
        });
    }
};
