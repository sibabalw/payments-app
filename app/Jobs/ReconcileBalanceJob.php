<?php

namespace App\Jobs;

use App\Models\Business;
use App\Services\ReconciliationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ReconcileBalanceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    public function __construct(
        public ?int $businessId = null
    ) {}

    public function handle(ReconciliationService $reconciliationService): void
    {
        if ($this->businessId) {
            $business = Business::find($this->businessId);
            if ($business) {
                $result = $reconciliationService->reconcileBalance($business, true);
                Log::info('Balance reconciled', $result);
            }
        } else {
            $results = $reconciliationService->reconcileAll(true);
            Log::info('All balances reconciled', ['count' => count($results)]);
        }
    }
}
