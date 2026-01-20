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
        Schema::create('business_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->onDelete('cascade');
            $table->string('type'); // email_payment_success, email_payslip, payslip_pdf, etc.
            $table->string('name');
            $table->string('preset')->nullable(); // Which preset template they chose: 'default', 'modern', 'minimal'
            $table->json('content'); // Stores the template structure/blocks for drag-drop editor
            $table->text('compiled_html')->nullable(); // The final rendered HTML
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['business_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('business_templates');
    }
};
