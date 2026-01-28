<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaction_reversals', function (Blueprint $table) {
            $table->id();
            $table->string('reversible_type', 128); // PayrollJob, PaymentJob
            $table->unsignedBigInteger('reversible_id');
            $table->string('reversal_type', 32); // automatic, manual
            $table->string('reason', 255)->nullable();
            $table->string('status', 16)->default('pending'); // pending, completed, failed
            $table->foreignId('reversed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->json('metadata')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->timestamps();

            $table->index(['reversible_type', 'reversible_id']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_reversals');
    }
};
