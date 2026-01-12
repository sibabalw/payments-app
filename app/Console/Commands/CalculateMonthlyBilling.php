<?php

namespace App\Console\Commands;

use App\Models\Business;
use App\Services\BillingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CalculateMonthlyBilling extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payments:calculate-monthly-billing 
                            {--month= : Month to calculate (YYYY-MM format, defaults to previous month)}
                            {--business_id= : Calculate for specific business only}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate monthly billing records with fixed subscription fees for previous month';

    /**
     * Execute the console command.
     */
    public function handle(BillingService $billingService): int
    {
        $month = $this->option('month') ?? now()->subMonth()->format('Y-m');
        $businessId = $this->option('business_id');

        $this->info("Calculating monthly billing for {$month}...");

        $query = Business::query();
        if ($businessId) {
            $query->where('id', $businessId);
        }

        $businesses = $query->get();

        if ($businesses->isEmpty()) {
            $this->warn('No businesses found.');
            return Command::SUCCESS;
        }

        $this->info("Found {$businesses->count()} business(es).");

        $processed = 0;
        $errors = 0;

        foreach ($businesses as $business) {
            try {
                $billing = $billingService->generateMonthlyBilling($business, $month);
                
                $subscriptionFee = $billingService->getSubscriptionFee($business);
                $businessType = $billingService->getBusinessType($business);

                $this->info("Business #{$business->id} ({$business->name}):");
                $this->line("  Type: {$businessType}");
                $this->line("  Subscription Fee: R" . number_format($subscriptionFee, 2));
                $this->line("  Deposit Fees: R" . number_format($billing->total_deposit_fees, 2));
                $this->line("  Total: R" . number_format($subscriptionFee + $billing->total_deposit_fees, 2));

                $processed++;
            } catch (\Exception $e) {
                $this->error("Failed to process business #{$business->id}: {$e->getMessage()}");
                Log::error('Failed to calculate monthly billing', [
                    'business_id' => $business->id,
                    'month' => $month,
                    'error' => $e->getMessage(),
                ]);
                $errors++;
            }
        }

        $this->info("\nProcessed: {$processed}, Errors: {$errors}");

        return Command::SUCCESS;
    }
}
