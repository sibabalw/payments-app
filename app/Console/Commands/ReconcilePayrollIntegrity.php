<?php

namespace App\Console\Commands;

use App\Models\Business;
use App\Models\PayrollJob;
use App\Services\EscrowService;
use App\Services\PayrollValidationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReconcilePayrollIntegrity extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payroll:reconcile 
                            {--business= : Reconcile specific business ID}
                            {--fix : Automatically fix issues where possible}
                            {--escrow-only : Only reconcile escrow balances}
                            {--payroll-only : Only reconcile payroll integrity}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reconcile escrow balances and payroll integrity';

    public function __construct(
        protected EscrowService $escrowService,
        protected PayrollValidationService $validationService
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $fix = $this->option('fix');
        $businessId = $this->option('business');
        $escrowOnly = $this->option('escrow-only');
        $payrollOnly = $this->option('payroll-only');

        $this->info('Starting payroll integrity reconciliation...');
        $this->newLine();

        $issues = 0;
        $fixed = 0;

        // Reconcile escrow balances
        if (! $payrollOnly) {
            $this->info('Reconciling escrow balances...');
            $result = $this->reconcileEscrowBalances($businessId, $fix);
            $issues += $result['issues'];
            $fixed += $result['fixed'];
        }

        // Reconcile payroll integrity
        if (! $escrowOnly) {
            $this->info('Reconciling payroll integrity...');
            $result = $this->reconcilePayrollIntegrity($businessId, $fix);
            $issues += $result['issues'];
            $fixed += $result['fixed'];
        }

        $this->newLine();
        $this->info('Reconciliation complete!');
        $this->table(
            ['Type', 'Count'],
            [
                ['Issues Found', $issues],
                ['Issues Fixed', $fixed],
            ]
        );

        return $issues > 0 && ! $fix ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * Reconcile escrow balances
     */
    protected function reconcileEscrowBalances(?int $businessId, bool $fix): array
    {
        $issues = 0;
        $fixed = 0;

        $query = Business::query();
        if ($businessId) {
            $query->where('id', $businessId);
        }

        $businesses = $query->get();
        $bar = $this->output->createProgressBar($businesses->count());
        $bar->start();

        foreach ($businesses as $business) {
            $storedBalance = $this->escrowService->getAvailableBalance($business, false, false);
            $calculatedBalance = $this->escrowService->recalculateBalance($business);

            $difference = abs($storedBalance - $calculatedBalance);

            if ($difference > 0.01) {
                $issues++;
                $this->newLine();
                $this->warn("Business #{$business->id}: Balance mismatch");
                $this->line('  Stored: '.number_format($storedBalance, 2));
                $this->line('  Calculated: '.number_format($calculatedBalance, 2));
                $this->line('  Difference: '.number_format($difference, 2));

                if ($fix) {
                    DB::table('businesses')
                        ->where('id', $business->id)
                        ->update(['escrow_balance' => $calculatedBalance]);

                    $this->info('  Fixed: Updated balance to '.number_format($calculatedBalance, 2));
                    $fixed++;

                    Log::info('Escrow balance reconciled', [
                        'business_id' => $business->id,
                        'old_balance' => $storedBalance,
                        'new_balance' => $calculatedBalance,
                        'difference' => $difference,
                    ]);
                }
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        return compact('issues', 'fixed');
    }

    /**
     * Reconcile payroll integrity
     */
    protected function reconcilePayrollIntegrity(?int $businessId, bool $fix): array
    {
        $issues = 0;
        $fixed = 0;

        $query = PayrollJob::query()
            ->whereIn('status', ['pending', 'processing', 'succeeded'])
            ->orderBy('created_at', 'desc')
            ->limit(1000);

        if ($businessId) {
            $query->whereHas('payrollSchedule', function ($q) use ($businessId) {
                $q->where('business_id', $businessId);
            });
        }

        $jobs = $query->get();
        $bar = $this->output->createProgressBar($jobs->count());
        $bar->start();

        foreach ($jobs as $job) {
            $validation = $this->validationService->validatePayrollJob($job);

            if (! $validation['valid']) {
                $issues += count($validation['errors']);

                if ($this->output->isVerbose()) {
                    $this->newLine();
                    $this->warn("Job #{$job->id}: Validation errors");
                    foreach ($validation['errors'] as $error) {
                        $this->line("  - {$error}");
                    }
                }

                if ($fix && $job->status === 'pending') {
                    // Only fix pending jobs to avoid modifying historical data
                    $fixed += $this->fixJobIssues($job, $validation);
                }
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        return compact('issues', 'fixed');
    }

    /**
     * Fix job issues where possible
     */
    protected function fixJobIssues(PayrollJob $job, array $validation): int
    {
        $fixed = 0;

        // Fix negative net salary
        if ($job->net_salary < 0) {
            $job->update(['net_salary' => 0]);
            $job->updateStatus('failed', 'Net salary was negative - corrected to 0');
            $fixed++;
        }

        return $fixed;
    }
}
