<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds individual indexes on business_user for dashboard query performance.
     * The unique constraint creates a composite index, but individual indexes
     * optimize WHERE EXISTS subqueries that filter by user_id or business_id alone.
     */
    public function up(): void
    {
        Schema::table('business_user', function (Blueprint $table) {
            // Index for WHERE EXISTS queries filtering by user_id
            // (e.g., DashboardController::getBusinessInfo)
            if (! $this->hasIndex('business_user', 'business_user_user_id_idx')) {
                $table->index('user_id', 'business_user_user_id_idx');
            }

            // Index for queries filtering by business_id
            if (! $this->hasIndex('business_user', 'business_user_business_id_idx')) {
                $table->index('business_id', 'business_user_business_id_idx');
            }
        });

        // businesses.user_id already has an index from foreign key constraint
        // but we ensure it exists for clarity
        Schema::table('businesses', function (Blueprint $table) {
            // Check if index exists (foreign keys auto-create indexes, but verify)
            if (! $this->hasIndex('businesses', 'businesses_user_id_idx')) {
                $table->index('user_id', 'businesses_user_id_idx');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('business_user', function (Blueprint $table) {
            if ($this->hasIndex('business_user', 'business_user_user_id_idx')) {
                $table->dropIndex('business_user_user_id_idx');
            }
            if ($this->hasIndex('business_user', 'business_user_business_id_idx')) {
                $table->dropIndex('business_user_business_id_idx');
            }
        });

        Schema::table('businesses', function (Blueprint $table) {
            // Only drop if we created it (foreign key indexes shouldn't be dropped)
            // In practice, this might be a no-op if the index was from FK
            if ($this->hasIndex('businesses', 'businesses_user_id_idx')) {
                // Check if it's not a foreign key index before dropping
                $indexes = Schema::getIndexes('businesses');
                $isForeignKeyIndex = false;
                foreach ($indexes as $index) {
                    if ($index['name'] === 'businesses_user_id_idx' && isset($index['columns']) && in_array('user_id', $index['columns'])) {
                        // This is likely from FK, don't drop
                        $isForeignKeyIndex = true;
                        break;
                    }
                }
                if (! $isForeignKeyIndex) {
                    $table->dropIndex('businesses_user_id_idx');
                }
            }
        });
    }

    /**
     * Check if an index exists on a table.
     */
    private function hasIndex(string $table, string $indexName): bool
    {
        try {
            $indexes = Schema::getIndexes($table);

            foreach ($indexes as $index) {
                if ($index['name'] === $indexName) {
                    return true;
                }
            }
        } catch (\Exception $e) {
            // If table doesn't exist or method not available, return false
            return false;
        }

        return false;
    }
};
