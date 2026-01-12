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
        Schema::table('businesses', function (Blueprint $table) {
            $table->enum('status', ['active', 'suspended', 'banned'])->default('active')->after('user_id');
            $table->text('status_reason')->nullable()->after('status');
            $table->timestamp('status_changed_at')->nullable()->after('status_reason');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn(['status', 'status_reason', 'status_changed_at']);
        });
    }
};
