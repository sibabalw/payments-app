<?php

namespace App\Console\Commands;

use App\Models\Business;
use App\Services\ReconciliationService;
use Illuminate\Console\Command;

class ReconcileBalances extends Command
{
    protected $signature = 'reconcile:balances 
                            {--business= : Reconcile specific business ID}
                            {--auto-fix : Automatically fix minor discrepancies}';

    protected $description = 'Reconcile escrow balances with ledger and calculated balances';

    public function __construct(
        protected ReconciliationService $reconciliationService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $businessId = $this->option('business');
        $autoFix = $this->option('auto-fix');

        if ($businessId) {
            $business = Business::find($businessId);
            if (! $business) {
                $this->error("Business #{$businessId} not found.");

                return Command::FAILURE;
            }

            $result = $this->reconciliationService->reconcileBalance($business, $autoFix);
            $this->displayResult($result);
        } else {
            $results = $this->reconciliationService->reconcileAll($autoFix);
            $this->displayResults($results);
        }

        return Command::SUCCESS;
    }

    protected function displayResult(array $result): void
    {
        $this->info("Business #{$result['business_id']}:");
        $this->line("  Stored: {$result['stored_balance']}");
        $this->line("  Calculated: {$result['calculated_balance']}");
        $this->line("  Ledger: {$result['ledger_balance']}");

        if ($result['reconciled']) {
            $this->info('  ✓ Reconciled');
        } else {
            $this->warn('  ✗ Discrepancies found:');
            foreach ($result['discrepancies'] as $discrepancy) {
                $this->line("    - {$discrepancy['type']}: {$discrepancy['difference']}");
            }
        }
    }

    protected function displayResults(array $results): void
    {
        $reconciled = 0;
        $discrepancies = 0;

        foreach ($results as $result) {
            if ($result['reconciled']) {
                $reconciled++;
            } else {
                $discrepancies++;
            }
        }

        $this->info("Reconciled: {$reconciled}, Discrepancies: {$discrepancies}");
    }
}
