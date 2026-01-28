<?php

namespace App\Console\Commands;

use App\Services\SettlementService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ProcessSettlementWindow extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'settlement:process-window 
                            {--window-type=hourly : Settlement window type (hourly, daily, custom)}
                            {--window-id= : Process specific window ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process settlement windows for batched transaction execution';

    public function __construct(
        protected SettlementService $settlementService
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $windowId = $this->option('window-id');
        $windowType = $this->option('window-type');

        if ($windowId) {
            // Process specific window
            $success = $this->settlementService->processWindow((int) $windowId);
            if ($success) {
                $this->info("Settlement window #{$windowId} processed successfully.");
            } else {
                $this->error("Failed to process settlement window #{$windowId}.");

                return Command::FAILURE;
            }
        } else {
            // Process all pending windows of specified type
            $windows = DB::table('settlement_windows')
                ->where('window_type', $windowType)
                ->where('status', 'pending')
                ->where('window_end', '<=', now())
                ->get();

            if ($windows->isEmpty()) {
                $this->info('No pending settlement windows found.');

                return Command::SUCCESS;
            }

            $this->info("Found {$windows->count()} pending settlement window(s).");

            $processed = 0;
            $failed = 0;

            foreach ($windows as $window) {
                try {
                    $success = $this->settlementService->processWindow($window->id);
                    if ($success) {
                        $processed++;
                        $this->info("Processed window #{$window->id} ({$window->transaction_count} transactions, {$window->total_amount} total).");
                    } else {
                        $failed++;
                    }
                } catch (\Exception $e) {
                    $failed++;
                    $this->error("Failed to process window #{$window->id}: {$e->getMessage()}");
                }
            }

            $this->info("Processed {$processed} window(s), {$failed} failed.");
        }

        return Command::SUCCESS;
    }
}
