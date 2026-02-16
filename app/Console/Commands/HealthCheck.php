<?php

namespace App\Console\Commands;

use App\Services\MonitoringService;
use Illuminate\Console\Command;

class HealthCheck extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'health:check 
                            {--metrics : Show transaction metrics}
                            {--format=text : Output format (text, json)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run system health checks and display metrics';

    public function __construct(
        protected MonitoringService $monitoringService
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $format = $this->option('format');
        $showMetrics = $this->option('metrics');

        // Run health checks
        $health = $this->monitoringService->runHealthChecks();

        if ($format === 'json') {
            $output = ['health' => $health];

            if ($showMetrics) {
                $output['metrics'] = $this->monitoringService->getTransactionMetrics();
            }

            $this->line(json_encode($output, JSON_PRETTY_PRINT));

            return $health['overall'] === 'healthy' ? Command::SUCCESS : Command::FAILURE;
        }

        // Text format
        $this->info('System Health Check');
        $this->line('==================');
        $this->line('');

        $overallStatus = $health['overall'] === 'healthy' ? '✓ Healthy' : '✗ Unhealthy';
        $this->line("Overall Status: {$overallStatus}");
        $this->line('');

        $this->info('Health Checks:');
        foreach ($health['checks'] as $name => $check) {
            $status = $check['healthy'] ? '✓' : '✗';
            $this->line("  {$status} {$name}: {$check['message']}");

            if (isset($check['error'])) {
                $this->line("    Error: {$check['error']}");
            }
        }

        if ($showMetrics) {
            $this->line('');
            $this->info('Transaction Metrics (Last 7 Days):');
            $metrics = $this->monitoringService->getTransactionMetrics();

            $this->line('  Payroll:');
            $this->line("    Total: {$metrics['payroll']['total']}");
            $this->line("    Succeeded: {$metrics['payroll']['succeeded']}");
            $this->line("    Failed: {$metrics['payroll']['failed']}");
            $this->line("    Success Rate: {$metrics['payroll']['success_rate']}%");
            if ($metrics['payroll']['avg_processing_time_ms']) {
                $this->line("    Avg Processing Time: {$metrics['payroll']['avg_processing_time_ms']}ms");
            }

            $this->line('  Payment:');
            $this->line("    Total: {$metrics['payment']['total']}");
            $this->line("    Succeeded: {$metrics['payment']['succeeded']}");
            $this->line("    Failed: {$metrics['payment']['failed']}");
            $this->line("    Success Rate: {$metrics['payment']['success_rate']}%");
            if ($metrics['payment']['avg_processing_time_ms']) {
                $this->line("    Avg Processing Time: {$metrics['payment']['avg_processing_time_ms']}ms");
            }
        }

        return $health['overall'] === 'healthy' ? Command::SUCCESS : Command::FAILURE;
    }
}
