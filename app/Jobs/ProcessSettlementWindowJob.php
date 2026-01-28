<?php

namespace App\Jobs;

use App\Helpers\LogContext;
use App\Services\SettlementBatchService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessSettlementWindowJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The maximum number of seconds a job can run before timing out.
     */
    public int $timeout = 600; // 10 minutes for large batches

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $windowId
    ) {}

    /**
     * Execute the job.
     */
    public function handle(SettlementBatchService $batchService): void
    {
        try {
            LogContext::info('Processing settlement window', LogContext::create(
                null,
                null,
                null,
                'settlement_window_job',
                null,
                ['window_id' => $this->windowId]
            ));

            $result = $batchService->processWindow($this->windowId);

            LogContext::info('Settlement window processed successfully', LogContext::create(
                null,
                null,
                null,
                'settlement_window_job',
                null,
                [
                    'window_id' => $this->windowId,
                    'result' => $result,
                ]
            ));
        } catch (\Exception $e) {
            LogContext::error('Settlement window processing failed', LogContext::create(
                null,
                null,
                null,
                'settlement_window_job',
                null,
                [
                    'window_id' => $this->windowId,
                    'exception' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]
            ));

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        LogContext::error('Settlement window job permanently failed', LogContext::create(
            null,
            null,
            null,
            'settlement_window_job',
            null,
            [
                'window_id' => $this->windowId,
                'exception' => $exception->getMessage(),
            ]
        ));

        // Mark window as failed
        \DB::table('settlement_windows')
            ->where('id', $this->windowId)
            ->update(['status' => 'failed']);
    }
}
