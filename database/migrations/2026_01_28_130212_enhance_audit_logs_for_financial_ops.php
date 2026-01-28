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
        if (Schema::hasTable('audit_logs')) {
            Schema::table('audit_logs', function (Blueprint $table) {
                if (! Schema::hasColumn('audit_logs', 'correlation_id')) {
                    $table->string('correlation_id', 64)->nullable()->after('model_id');
                }
                if (! Schema::hasColumn('audit_logs', 'before_values')) {
                    $table->json('before_values')->nullable()->after('changes');
                }
                if (! Schema::hasColumn('audit_logs', 'after_values')) {
                    $table->json('after_values')->nullable()->after('before_values');
                }
                if (! Schema::hasColumn('audit_logs', 'metadata')) {
                    $table->json('metadata')->nullable()->after('after_values');
                }

                // Add index for correlation_id for tracing
                if (! $this->hasIndex('audit_logs', 'audit_logs_correlation_id_index')) {
                    $table->index('correlation_id');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('audit_logs')) {
            Schema::table('audit_logs', function (Blueprint $table) {
                if (Schema::hasColumn('audit_logs', 'correlation_id')) {
                    $table->dropIndex('audit_logs_correlation_id_index');
                    $table->dropColumn('correlation_id');
                }
                if (Schema::hasColumn('audit_logs', 'before_values')) {
                    $table->dropColumn('before_values');
                }
                if (Schema::hasColumn('audit_logs', 'after_values')) {
                    $table->dropColumn('after_values');
                }
                if (Schema::hasColumn('audit_logs', 'metadata')) {
                    $table->dropColumn('metadata');
                }
            });
        }
    }

    /**
     * Check if index exists
     */
    protected function hasIndex(string $table, string $indexName): bool
    {
        $connection = Schema::getConnection();
        $database = $connection->getDatabaseName();
        $indexes = $connection->select("SHOW INDEX FROM {$table} WHERE Key_name = ?", [$indexName]);

        return ! empty($indexes);
    }
};
