<?php

namespace App\Console\Commands;

use App\Models\Business;
use App\Models\MonthlyBilling;
use App\Services\BillingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ChargeSubscriptions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'billing:charge-subscriptions 
                            {--month= : Specific month to process (YYYY-MM format)}
                            {--business= : Specific business ID to process}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process pending monthly subscription charges for businesses';

    /**
     * Execute the console command.
     */
    public function handle(BillingService $billingService): int
    {
        $month = $this->option('month') ?? now()->subMonth()->format('Y-m');
        $businessId = $this->option('business');

        $this->info("Processing subscription charges for {$month}...");

        // Build query for pending billings
        $query = MonthlyBilling::where('status', 'pending')
            ->where('billing_month', $month);

        if ($businessId) {
            $query->where('business_id', $businessId);
        }

        $billings = $query->with('business')->get();

        if ($billings->isEmpty()) {
            $this->warn('No pending billings found for the specified criteria.');

            return Command::SUCCESS;
        }

        $this->info("Found {$billings->count()} pending billing(s).");

        $processed = 0;
        $succeeded = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($billings as $billing) {
            $business = $billing->business;

            if (! $business) {
                $this->warn("Billing #{$billing->id} has no associated business. Skipping.");
                $skipped++;

                continue;
            }

            // Check if business has bank account details
            if (! $business->hasBankAccountDetails()) {
                $this->warn("Business #{$business->id} ({$business->name}) has no bank account details. Skipping.");
                $skipped++;

                continue;
            }

            try {
                $this->line("Processing billing #{$billing->id} for business #{$business->id} ({$business->name})...");

                $success = $billingService->processSubscriptionFee($business, $billing);

                if ($success) {
                    $this->info('  ✓ Successfully charged R'.number_format($billing->subscription_fee, 2));
                    $succeeded++;
                } else {
                    $this->error('  ✗ Failed to charge subscription fee');
                    $failed++;
                }

                $processed++;
            } catch (\Exception $e) {
                $this->error("  ✗ Error processing billing #{$billing->id}: {$e->getMessage()}");
                Log::error('Failed to process subscription charge', [
                    'billing_id' => $billing->id,
                    'business_id' => $business->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                $failed++;
                $processed++;
            }
        }

        $this->newLine();
        $this->info('Summary:');
        $this->line("  Processed: {$processed}");
        $this->line("  Succeeded: {$succeeded}");
        $this->line("  Failed: {$failed}");
        $this->line("  Skipped: {$skipped}");

        return Command::SUCCESS;
    }
}
