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
        Schema::create('compliance_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->onDelete('cascade');
            $table->foreignId('employee_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('type'); // ui19, emp201, irp5
            $table->string('period'); // e.g., "2026-01" for monthly, "2025/2026" for tax year
            $table->string('status')->default('draft'); // draft, generated, submitted
            $table->json('data')->nullable(); // Submission data
            $table->string('file_path')->nullable(); // Generated file path
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'type', 'period']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('compliance_submissions');
    }
};
