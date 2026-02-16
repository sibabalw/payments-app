<?php

namespace App\Console\Commands;

use App\Jobs\CheckEscrowBalanceJob;
use Illuminate\Console\Command;

class CheckEscrowBalanceCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'escrow:check-balance
                            {--business= : Check specific business by ID}
                            {--sync : Run synchronously instead of dispatching job}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check if upcoming payments and payroll exceed escrow balance and send warning emails';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Checking escrow balances for upcoming payments and payroll...');

        if ($this->option('sync')) {
            // Run synchronously
            $job = new CheckEscrowBalanceJob;
            $job->handle();
            $this->info('Escrow balance check completed synchronously.');
        } else {
            // Dispatch to queue
            CheckEscrowBalanceJob::dispatch();
            $this->info('Escrow balance check job dispatched to queue.');
        }

        return Command::SUCCESS;
    }
}
