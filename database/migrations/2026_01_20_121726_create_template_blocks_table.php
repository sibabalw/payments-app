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
        // Create template blocks table
        Schema::create('template_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_template_id')->constrained()->onDelete('cascade');
            $table->string('block_id', 50); // Client-side ID for drag-drop
            $table->string('type', 30); // header, text, button, divider, table, footer
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['business_template_id', 'sort_order']);
        });

        // Create block properties table (key-value pairs for each block)
        Schema::create('template_block_properties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_block_id')->constrained()->onDelete('cascade');
            $table->string('key', 50); // backgroundColor, textColor, content, label, etc.
            $table->text('value')->nullable();
            $table->timestamps();

            $table->unique(['template_block_id', 'key']);
            $table->index('template_block_id');
        });

        // Create table rows table (for table blocks specifically)
        Schema::create('template_table_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_block_id')->constrained()->onDelete('cascade');
            $table->string('label', 100);
            $table->string('value', 255);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['template_block_id', 'sort_order']);
        });

        // Remove the JSON content column from business_templates
        Schema::table('business_templates', function (Blueprint $table) {
            $table->dropColumn('content');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Add back the JSON content column
        Schema::table('business_templates', function (Blueprint $table) {
            $table->json('content')->nullable()->after('preset');
        });

        Schema::dropIfExists('template_table_rows');
        Schema::dropIfExists('template_block_properties');
        Schema::dropIfExists('template_blocks');
    }
};
