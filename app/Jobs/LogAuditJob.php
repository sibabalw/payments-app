<?php

namespace App\Jobs;

use App\Models\AuditLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class LogAuditJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 60;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 30;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $action,
        public ?int $userId = null,
        public ?int $businessId = null,
        public ?string $modelType = null,
        public ?int $modelId = null,
        public ?array $changes = null,
        public ?string $ipAddress = null,
        public ?string $userAgent = null
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        AuditLog::create([
            'user_id' => $this->userId,
            'business_id' => $this->businessId,
            'action' => $this->action,
            'model_type' => $this->modelType,
            'model_id' => $this->modelId,
            'changes' => $this->changes,
            'ip_address' => $this->ipAddress,
            'user_agent' => $this->userAgent,
        ]);
    }

    /**
     * Calculate the number of seconds to wait before retrying the job.
     */
    public function backoff(): array
    {
        return [
            10,   // 10 seconds
            30,   // 30 seconds
            60,   // 1 minute
        ];
    }
}
