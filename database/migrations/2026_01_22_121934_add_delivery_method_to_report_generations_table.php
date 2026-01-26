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
        Schema::table('report_generations', function (Blueprint $table) {
            $table->string('delivery_method')->default('email')->after('format'); // 'email' or 'download'
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('report_generations', function (Blueprint $table) {
            $table->dropColumn('delivery_method');
        });
    }
};
