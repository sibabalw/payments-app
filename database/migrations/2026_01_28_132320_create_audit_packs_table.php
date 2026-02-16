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
        Schema::create('audit_packs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->nullable()->constrained()->onDelete('set null');
            $table->date('date_from');
            $table->date('date_to');
            $table->string('pack_filename');
            $table->string('pack_hash', 64); // SHA256 hash
            $table->integer('ledger_entry_count')->default(0);
            $table->integer('audit_log_count')->default(0);
            $table->integer('reversal_count')->default(0);
            $table->foreignId('exported_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('exported_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'date_from', 'date_to']);
            $table->index('exported_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_packs');
    }
};
