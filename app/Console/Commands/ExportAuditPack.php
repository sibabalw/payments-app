<?php

namespace App\Console\Commands;

use App\Models\Business;
use App\Services\AuditExportService;
use Illuminate\Console\Command;

class ExportAuditPack extends Command
{
    protected $signature = 'audit:export-pack 
                            {--business= : Business ID}
                            {--from= : Start date (Y-m-d)}
                            {--to= : End date (Y-m-d)}';

    protected $description = 'Export audit pack (ledger entries, audit logs, reversals) for regulatory compliance';

    public function __construct(
        protected AuditExportService $auditExportService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $businessId = $this->option('business');
        $dateFrom = $this->option('from') ? new \DateTime($this->option('from')) : now()->subYear();
        $dateTo = $this->option('to') ? new \DateTime($this->option('to')) : now();

        $business = $businessId ? Business::find($businessId) : null;

        if ($businessId && ! $business) {
            $this->error("Business #{$businessId} not found.");

            return Command::FAILURE;
        }

        $this->info('Exporting audit pack...');
        $this->line('  Business: '.($business ? "#{$business->id} - {$business->name}" : 'All businesses'));
        $this->line("  Date range: {$dateFrom->format('Y-m-d')} to {$dateTo->format('Y-m-d')}");

        try {
            $zipPath = $this->auditExportService->exportAuditPack($business, $dateFrom, $dateTo);

            $this->info("Audit pack exported successfully: {$zipPath}");

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Export failed: {$e->getMessage()}");

            return Command::FAILURE;
        }
    }
}
