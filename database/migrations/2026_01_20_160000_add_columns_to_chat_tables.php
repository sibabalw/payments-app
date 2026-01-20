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
        // Add columns to chat_conversations
        Schema::table('chat_conversations', function (Blueprint $table) {
            $table->foreignId('user_id')->after('id')->constrained()->onDelete('cascade');
            $table->foreignId('business_id')->after('user_id')->constrained()->onDelete('cascade');
            $table->string('title')->nullable()->after('business_id');

            $table->index(['user_id', 'business_id']);
            $table->index(['business_id', 'updated_at']);
        });

        // Add columns to chat_messages
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->foreignId('conversation_id')->after('id')->constrained('chat_conversations')->onDelete('cascade');
            $table->enum('role', ['user', 'assistant'])->default('user')->after('conversation_id');
            $table->text('content')->after('role');
            $table->json('metadata')->nullable()->after('content');

            $table->index(['conversation_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->dropForeign(['conversation_id']);
            $table->dropIndex(['conversation_id', 'created_at']);
            $table->dropColumn(['conversation_id', 'role', 'content', 'metadata']);
        });

        Schema::table('chat_conversations', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropForeign(['business_id']);
            $table->dropIndex(['user_id', 'business_id']);
            $table->dropIndex(['business_id', 'updated_at']);
            $table->dropColumn(['user_id', 'business_id', 'title']);
        });
    }
};
