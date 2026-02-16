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
        Schema::create('balance_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('account_type', 32); // ESCROW, PAYROLL, etc.
            $table->foreignId('business_id')->constrained()->onDelete('cascade');
            $table->date('snapshot_date');
            $table->bigInteger('balance_minor_units'); // Balance in minor units (cents)
            $table->unsignedBigInteger('sequence_number'); // Last sequence included in snapshot
            $table->string('checksum', 64); // SHA256 hash of all entries up to sequence
            $table->integer('entry_count')->default(0); // Number of entries included
            $table->timestamps();

            $table->unique(['account_type', 'business_id', 'snapshot_date'], 'uk_account_snapshot_date');
            $table->index(['business_id', 'account_type', 'snapshot_date']);
            $table->index('snapshot_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('balance_snapshots');
    }
};
