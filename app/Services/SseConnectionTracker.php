<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SseConnectionTracker
{
    private const CACHE_PREFIX = 'sse_connections:';

    private const TTL = 600; // 10 minutes

    /**
     * Track an SSE connection
     */
    public function track(int $reportGenerationId, int $userId): void
    {
        $key = $this->getKey($reportGenerationId, $userId);
        Cache::put($key, [
            'report_generation_id' => $reportGenerationId,
            'user_id' => $userId,
            'connected_at' => now()->toIso8601String(),
        ], self::TTL);

        Log::info('SSE connection tracked', [
            'report_generation_id' => $reportGenerationId,
            'user_id' => $userId,
        ]);
    }

    /**
     * Untrack an SSE connection
     */
    public function untrack(int $reportGenerationId, int $userId): void
    {
        $key = $this->getKey($reportGenerationId, $userId);
        Cache::forget($key);

        Log::info('SSE connection untracked', [
            'report_generation_id' => $reportGenerationId,
            'user_id' => $userId,
        ]);
    }

    /**
     * Get count of active SSE connections
     */
    public function getActiveConnectionCount(): int
    {
        // Note: This is approximate as cache keys expire automatically
        // For exact count, you'd need Redis SCAN or a dedicated tracking table
        $pattern = self::CACHE_PREFIX.'*';

        // Laravel cache doesn't support pattern matching directly
        // This would require Redis or a database table for accurate counting
        // For now, we rely on logs for monitoring

        return 0; // Placeholder - implement with Redis SCAN if needed
    }

    /**
     * Get all active connections for a report generation
     */
    public function getConnectionsForReport(int $reportGenerationId): array
    {
        // Would need Redis SCAN or database table for this
        // For now, return empty array
        return [];
    }

    /**
     * Clean up stale connections (called by scheduled job)
     */
    public function cleanupStaleConnections(): int
    {
        // Connections expire automatically via TTL
        // This method can be used for manual cleanup if needed
        return 0;
    }

    private function getKey(int $reportGenerationId, int $userId): string
    {
        return self::CACHE_PREFIX.$reportGenerationId.':'.$userId;
    }
}
