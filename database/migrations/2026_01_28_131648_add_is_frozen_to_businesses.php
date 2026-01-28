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
        if (Schema::hasTable('businesses')) {
            Schema::table('businesses', function (Blueprint $table) {
                if (! Schema::hasColumn('businesses', 'is_frozen')) {
                    $table->boolean('is_frozen')->default(false)->after('escrow_balance');
                }
                if (! Schema::hasColumn('businesses', 'hold_amount')) {
                    $table->decimal('hold_amount', 15, 2)->default(0)->after('is_frozen');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('businesses')) {
            Schema::table('businesses', function (Blueprint $table) {
                if (Schema::hasColumn('businesses', 'is_frozen')) {
                    $table->dropColumn('is_frozen');
                }
                if (Schema::hasColumn('businesses', 'hold_amount')) {
                    $table->dropColumn('hold_amount');
                }
            });
        }
    }
};
