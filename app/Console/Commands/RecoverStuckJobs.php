<?php

namespace App\Console\Commands;

use App\Services\RecoveryService;
use Illuminate\Console\Command;

class RecoverStuckJobs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'jobs:recover-stuck {--limit=100 : Maximum number of jobs to process per type} {--type=all : Job type to recover (payroll, payment, or all)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recover stuck and failed jobs';

    public function __construct(
        protected RecoveryService $recoveryService
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        $type = $this->option('type');

        $this->info('Starting job recovery...');

        if ($type === 'all' || $type === 'payroll') {
            $this->info('Recovering stuck payroll jobs...');
            $payrollStuck = $this->recoveryService->recoverStuckPayrollJobs($limit);
            $this->displayResults('Payroll Stuck', $payrollStuck);

            $this->info('Retrying failed payroll jobs...');
            $payrollFailed = $this->recoveryService->retryFailedJobs('payroll', $limit);
            $this->displayResults('Payroll Failed', $payrollFailed);
        }

        if ($type === 'all' || $type === 'payment') {
            $this->info('Recovering stuck payment jobs...');
            $paymentStuck = $this->recoveryService->recoverStuckPaymentJobs($limit);
            $this->displayResults('Payment Stuck', $paymentStuck);

            $this->info('Retrying failed payment jobs...');
            $paymentFailed = $this->recoveryService->retryFailedJobs('payment', $limit);
            $this->displayResults('Payment Failed', $paymentFailed);
        }

        $this->info('Job recovery completed.');

        return Command::SUCCESS;
    }

    protected function displayResults(string $label, array $results): void
    {
        $this->line("  {$label}:");
        $detectedOrRetried = $results['detected'] ?? $results['retried'] ?? 0;
        $this->line("    Detected/Retried: {$detectedOrRetried}");
        $this->line('    Recovered: '.($results['recovered'] ?? 0));
        $this->line('    Failed: '.($results['failed'] ?? 0));
    }
}
