<?php

namespace App\Console\Commands;

use App\Services\JobSyncService;
use Illuminate\Console\Command;

class SyncJobsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'jobs:sync 
                            {--payment : Sync only payment jobs}
                            {--payroll : Sync only payroll jobs}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync pending payment and payroll jobs with the queue';

    /**
     * Execute the console command.
     */
    public function handle(JobSyncService $jobSyncService): int
    {
        $this->info('Syncing jobs with queue...');

        if ($this->option('payment')) {
            $result = $jobSyncService->syncPaymentJobs();
            $this->displayResults('Payment Jobs', $result);
        } elseif ($this->option('payroll')) {
            $result = $jobSyncService->syncPayrollJobs();
            $this->displayResults('Payroll Jobs', $result);
        } else {
            $results = $jobSyncService->syncAll();
            $this->displayResults('Payment Jobs', $results['payment_jobs']);
            $this->displayResults('Payroll Jobs', $results['payroll_jobs']);
        }

        $this->info('Job sync completed.');

        return Command::SUCCESS;
    }

    /**
     * Display sync results.
     */
    protected function displayResults(string $type, array $result): void
    {
        $this->line("{$type}:");
        $this->line("  Total: {$result['total']}");
        $this->line("  Synced: {$result['synced']}");
        $this->line("  Skipped: {$result['skipped']}");

        if ($result['errors'] > 0) {
            $this->error("  Errors: {$result['errors']}");
        } else {
            $this->line("  Errors: {$result['errors']}");
        }
    }
}
