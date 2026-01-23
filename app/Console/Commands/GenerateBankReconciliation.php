<?php

namespace App\Console\Commands;

use App\Models\PaymentJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class GenerateBankReconciliation extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payments:reconcile-bank 
                            {--business_id= : Filter by business ID}
                            {--start_date= : Start date (Y-m-d)}
                            {--end_date= : End date (Y-m-d)}
                            {--output= : Output file path (default: storage/app/reports)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate bank reconciliation report with payment job details for bank proof';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $businessId = $this->option('business_id');
        $startDate = $this->option('start_date');
        $endDate = $this->option('end_date');
        $outputPath = $this->option('output') ?? 'reports/bank_reconciliation_'.now()->format('Y-m-d_His').'.csv';

        $query = PaymentJob::query()
            ->select(['payment_jobs.*'])
            ->join('payment_schedules', 'payment_jobs.payment_schedule_id', '=', 'payment_schedules.id')
            ->with(['paymentSchedule.business', 'recipient'])
            ->whereNotNull('payment_jobs.processed_at');

        if ($businessId) {
            $query->where('payment_schedules.business_id', $businessId);
        }

        if ($startDate) {
            $query->where('processed_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('processed_at', '<=', $endDate);
        }

        $jobs = $query->orderBy('processed_at', 'desc')->get();

        if ($jobs->isEmpty()) {
            $this->warn('No payment jobs found for the specified criteria.');

            return Command::SUCCESS;
        }

        $this->info("Found {$jobs->count()} payment job(s). Generating report...");

        $csvData = [];
        $csvData[] = [
            'Payment Job ID',
            'Transaction ID',
            'Business ID',
            'Business Name',
            'Schedule ID',
            'Schedule Name',
            'Receiver ID',
            'Receiver Name',
            'Amount',
            'Fee',
            'Total Cost',
            'Currency',
            'Status',
            'Processed At',
            'Error Message',
            'Created At',
            'Updated At',
        ];

        foreach ($jobs as $job) {
            $csvData[] = [
                $job->id,
                $job->transaction_id ?? 'N/A',
                $job->paymentSchedule->business_id,
                $job->paymentSchedule->business->name,
                $job->payment_schedule_id,
                $job->paymentSchedule->name,
                $job->recipient_id,
                $job->recipient?->name ?? 'N/A',
                number_format($job->amount, 2, '.', ''),
                number_format($job->fee, 2, '.', ''),
                number_format($job->amount + $job->fee, 2, '.', ''),
                $job->currency,
                $job->status,
                $job->processed_at?->toIso8601String() ?? 'N/A',
                $job->error_message ?? '',
                $job->created_at->toIso8601String(),
                $job->updated_at->toIso8601String(),
            ];
        }

        // Write CSV file
        $csvContent = '';
        foreach ($csvData as $row) {
            $csvContent .= implode(',', array_map(function ($field) {
                return '"'.str_replace('"', '""', $field).'"';
            }, $row))."\n";
        }

        Storage::put($outputPath, $csvContent);

        $fullPath = Storage::path($outputPath);
        $this->info('Bank reconciliation report generated successfully!');
        $this->info("File: {$fullPath}");
        $this->info("Total records: {$jobs->count()}");

        // Summary statistics
        $succeeded = $jobs->where('status', 'succeeded')->count();
        $failed = $jobs->where('status', 'failed')->count();
        $totalAmount = $jobs->sum('amount');
        $totalFees = $jobs->sum('fee');

        $this->table(
            ['Metric', 'Value'],
            [
                ['Succeeded', $succeeded],
                ['Failed', $failed],
                ['Total Amount', number_format($totalAmount, 2)],
                ['Total Fees', number_format($totalFees, 2)],
                ['Total Cost', number_format($totalAmount + $totalFees, 2)],
            ]
        );

        return Command::SUCCESS;
    }
}
