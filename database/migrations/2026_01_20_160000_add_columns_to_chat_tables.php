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
        // Add columns to chat_conversations (only if they don't exist)
        if (! Schema::hasColumn('chat_conversations', 'user_id')) {
            // Add column as nullable first
            Schema::table('chat_conversations', function (Blueprint $table) {
                $table->unsignedBigInteger('user_id')->nullable()->after('id');
            });
            // Set default values for existing rows (use first user or delete orphaned rows)
            $firstUserId = \DB::table('users')->value('id');
            if ($firstUserId) {
                \DB::table('chat_conversations')->whereNull('user_id')->update(['user_id' => $firstUserId]);
            } else {
                // No users exist, delete orphaned conversations
                \DB::table('chat_conversations')->whereNull('user_id')->delete();
            }
            // Now add foreign key constraint
            Schema::table('chat_conversations', function (Blueprint $table) {
                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            });
        }
        if (! Schema::hasColumn('chat_conversations', 'business_id')) {
            // Add column as nullable first
            Schema::table('chat_conversations', function (Blueprint $table) {
                $table->unsignedBigInteger('business_id')->nullable()->after('user_id');
            });
            // Set default values for existing rows
            \DB::statement('UPDATE chat_conversations cc 
                INNER JOIN users u ON cc.user_id = u.id 
                SET cc.business_id = COALESCE(
                    (SELECT id FROM businesses WHERE user_id = u.id LIMIT 1),
                    (SELECT id FROM businesses LIMIT 1),
                    u.current_business_id
                )
                WHERE cc.business_id IS NULL');
            // Delete rows that still have NULL business_id (orphaned rows)
            \DB::table('chat_conversations')->whereNull('business_id')->delete();
            // Now add foreign key constraint
            Schema::table('chat_conversations', function (Blueprint $table) {
                $table->foreign('business_id')->references('id')->on('businesses')->onDelete('cascade');
            });
        }
        if (! Schema::hasColumn('chat_conversations', 'title')) {
            Schema::table('chat_conversations', function (Blueprint $table) {
                $table->string('title')->nullable()->after('business_id');
            });
        }

        // Add indexes (will fail silently if they already exist)
        if (Schema::hasColumn('chat_conversations', 'user_id') && Schema::hasColumn('chat_conversations', 'business_id')) {
            try {
                Schema::table('chat_conversations', function (Blueprint $table) {
                    $table->index(['user_id', 'business_id']);
                });
            } catch (\Exception $e) {
                // Index might already exist, ignore
            }
            try {
                Schema::table('chat_conversations', function (Blueprint $table) {
                    $table->index(['business_id', 'updated_at']);
                });
            } catch (\Exception $e) {
                // Index might already exist, ignore
            }
        }

        // Add columns to chat_messages (only if they don't exist)
        if (! Schema::hasColumn('chat_messages', 'conversation_id')) {
            // Add column as nullable first
            Schema::table('chat_messages', function (Blueprint $table) {
                $table->unsignedBigInteger('conversation_id')->nullable()->after('id');
            });
            // Delete messages that reference non-existent conversations
            \DB::statement('DELETE FROM chat_messages WHERE conversation_id IS NOT NULL 
                AND conversation_id NOT IN (SELECT id FROM chat_conversations)');
            // Set default conversation_id for existing rows (use first conversation or delete orphaned rows)
            $firstConversationId = \DB::table('chat_conversations')->value('id');
            if ($firstConversationId) {
                \DB::table('chat_messages')->whereNull('conversation_id')->update(['conversation_id' => $firstConversationId]);
            } else {
                // No conversations exist, delete orphaned messages
                \DB::table('chat_messages')->whereNull('conversation_id')->delete();
            }
            // Now add foreign key constraint
            Schema::table('chat_messages', function (Blueprint $table) {
                $table->foreign('conversation_id')->references('id')->on('chat_conversations')->onDelete('cascade');
            });
        }
        if (! Schema::hasColumn('chat_messages', 'role')) {
            Schema::table('chat_messages', function (Blueprint $table) {
                $table->enum('role', ['user', 'assistant'])->default('user')->after('conversation_id');
            });
        }
        if (! Schema::hasColumn('chat_messages', 'content')) {
            Schema::table('chat_messages', function (Blueprint $table) {
                $table->text('content')->after('role');
            });
        }
        if (! Schema::hasColumn('chat_messages', 'metadata')) {
            Schema::table('chat_messages', function (Blueprint $table) {
                $table->json('metadata')->nullable()->after('content');
            });
        }

        // Add index (will fail silently if it already exists)
        if (Schema::hasColumn('chat_messages', 'conversation_id')) {
            try {
                Schema::table('chat_messages', function (Blueprint $table) {
                    $table->index(['conversation_id', 'created_at']);
                });
            } catch (\Exception $e) {
                // Index might already exist, ignore
            }
        }
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
