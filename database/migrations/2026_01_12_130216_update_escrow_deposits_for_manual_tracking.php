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
        // Update existing 'completed' status to 'confirmed' before changing enum
        \DB::table('escrow_deposits')->where('status', 'completed')->update(['status' => 'confirmed']);

        Schema::table('escrow_deposits', function (Blueprint $table) {
            // Change status enum to include 'confirmed' instead of 'completed'
            $table->dropColumn('status');
        });

        Schema::table('escrow_deposits', function (Blueprint $table) {
            $table->enum('status', ['pending', 'confirmed', 'failed'])->default('pending')->after('currency');
            $table->enum('entry_method', ['app', 'manual'])->default('app')->after('status');
            $table->foreignId('entered_by')->nullable()->after('entry_method')->constrained('users')->onDelete('set null');
            
            $table->index('entry_method');
            $table->index('entered_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('escrow_deposits', function (Blueprint $table) {
            $table->dropIndex(['entry_method']);
            $table->dropIndex(['entered_by']);
            $table->dropForeign(['entered_by']);
            $table->dropColumn(['entry_method', 'entered_by']);
        });

        Schema::table('escrow_deposits', function (Blueprint $table) {
            $table->dropColumn('status');
        });

        Schema::table('escrow_deposits', function (Blueprint $table) {
            $table->enum('status', ['pending', 'completed', 'failed'])->default('pending')->after('currency');
        });
    }
};
