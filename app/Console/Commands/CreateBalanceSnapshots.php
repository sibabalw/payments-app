<?php

namespace App\Console\Commands;

use App\Models\Business;
use App\Services\SnapshotService;
use Illuminate\Console\Command;

class CreateBalanceSnapshots extends Command
{
    protected $signature = 'snapshot:create-balances 
                            {--date= : Snapshot date (default: yesterday)}
                            {--account-type= : Specific account type (default: all)}
                            {--business= : Specific business ID}';

    protected $description = 'Create balance snapshots for fast reconciliation at scale';

    public function __construct(
        protected SnapshotService $snapshotService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $snapshotDate = $this->option('date')
            ? new \DateTime($this->option('date'))
            : now()->subDay();

        $accountType = $this->option('account-type');
        $businessId = $this->option('business');

        $accountTypes = $accountType
            ? [$accountType]
            : [\App\Services\FinancialLedgerService::ACCOUNT_ESCROW];

        $query = Business::query();
        if ($businessId) {
            $query->where('id', $businessId);
        }

        $businesses = $query->get();

        $this->info("Creating snapshots for {$businesses->count()} business(es) on {$snapshotDate->format('Y-m-d')}");

        $created = 0;
        $failed = 0;

        foreach ($businesses as $business) {
            foreach ($accountTypes as $type) {
                try {
                    $this->snapshotService->createSnapshot($business, $type, $snapshotDate);
                    $created++;
                    $this->line("  ✓ Created snapshot for business #{$business->id}, account: {$type}");
                } catch (\Exception $e) {
                    $failed++;
                    $this->error("  ✗ Failed for business #{$business->id}, account: {$type} - {$e->getMessage()}");
                }
            }
        }

        $this->info("Created {$created} snapshot(s), {$failed} failed.");

        return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
