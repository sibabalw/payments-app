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
        Schema::create('monthly_billings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->onDelete('cascade');
            $table->string('billing_month', 7); // YYYY-MM format
            $table->enum('business_type', ['small_business', 'other'])->default('small_business');
            $table->decimal('subscription_fee', 15, 2);
            $table->decimal('total_deposit_fees', 15, 2)->default(0);
            $table->enum('status', ['pending', 'paid', 'waived'])->default('pending');
            $table->timestamp('billed_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->unique(['business_id', 'billing_month']);
            $table->index(['business_id', 'status']);
            $table->index('billing_month');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('monthly_billings');
    }
};
