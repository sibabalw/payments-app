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
            $table->string('registration_number')->nullable()->after('business_type');
            $table->string('tax_id')->nullable()->after('registration_number');
            $table->string('email')->nullable()->after('tax_id');
            $table->string('phone')->nullable()->after('email');
            $table->string('website')->nullable()->after('phone');
            $table->string('street_address')->nullable()->after('website');
            $table->string('city')->nullable()->after('street_address');
            $table->string('postal_code')->nullable()->after('city');
            $table->string('country')->nullable()->after('postal_code');
            $table->text('description')->nullable()->after('country');
            $table->string('contact_person_name')->nullable()->after('description');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn([
                'registration_number',
                'tax_id',
                'email',
                'phone',
                'website',
                'street_address',
                'city',
                'postal_code',
                'country',
                'description',
                'contact_person_name',
            ]);
        });
    }
};
