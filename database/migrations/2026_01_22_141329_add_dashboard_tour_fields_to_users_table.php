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
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('has_completed_dashboard_tour')->default(false)->after('email_preferences');
            $table->timestamp('dashboard_tour_completed_at')->nullable()->after('has_completed_dashboard_tour');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['has_completed_dashboard_tour', 'dashboard_tour_completed_at']);
        });
    }
};
