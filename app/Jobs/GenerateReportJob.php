<?php

namespace App\Jobs;

use App\Mail\ReportReadyEmail;
use App\Models\Business;
use App\Models\ReportGeneration;
use App\Services\EmailService;
use Barryvdh\DomPDF\Facade\Pdf as PDF;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class GenerateReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 1;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 60;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 300;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public ReportGeneration $reportGeneration
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $this->reportGeneration->markAsProcessing();

            $parameters = $this->reportGeneration->parameters ?? [];
            $reportType = $this->reportGeneration->report_type;
            $format = $this->reportGeneration->format;
            $businessId = $parameters['business_id'] ?? null;
            $startDate = $parameters['start_date'] ?? null;
            $endDate = $parameters['end_date'] ?? null;

            // Get report data
            $reportController = app(\App\Http\Controllers\ReportController::class);
            $report = $reportController->getReportData($reportType, $businessId, $startDate, $endDate, $this->reportGeneration->user_id);

            // Generate file based on format
            $filename = $this->generateFilename($reportType, $startDate, $endDate, $format);
            $filePath = $this->generateFile($report, $reportType, $format, $filename, $businessId, $startDate, $endDate);

            // Mark as completed (atomic update ensures SSE sees consistent state)
            $this->reportGeneration->markAsCompleted($filePath, $filename);

            // Refresh to ensure we have the latest state
            $this->reportGeneration->refresh();

            // Send notification email only if delivery method is 'email'
            if ($this->reportGeneration->delivery_method === 'email') {
                $user = $this->reportGeneration->user;
                $emailService = app(EmailService::class);
                $emailService->send(
                    $user,
                    new ReportReadyEmail($user, $this->reportGeneration),
                    'report_ready'
                );
            }

            Log::info('Report generated successfully', [
                'report_generation_id' => $this->reportGeneration->id,
                'format' => $format,
                'report_type' => $reportType,
            ]);
        } catch (\Exception $e) {
            Log::error('Report generation failed', [
                'report_generation_id' => $this->reportGeneration->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Mark as failed (atomic update ensures SSE sees consistent state)
            $this->reportGeneration->markAsFailed($e->getMessage());

            // Refresh to ensure we have the latest state
            $this->reportGeneration->refresh();

            // Re-throw to mark job as failed
            throw $e;
        }
    }

    /**
     * Generate filename for the report.
     */
    private function generateFilename(string $reportType, ?string $startDate, ?string $endDate, string $format): string
    {
        $dateRange = '';
        if ($startDate && $endDate) {
            $dateRange = '_'.str_replace('-', '', $startDate).'_to_'.str_replace('-', '', $endDate);
        } elseif ($startDate) {
            $dateRange = '_from_'.str_replace('-', '', $startDate);
        } elseif ($endDate) {
            $dateRange = '_until_'.str_replace('-', '', $endDate);
        }

        $reportName = str_replace('_', '-', $reportType);

        return "{$reportName}{$dateRange}_".now()->format('Y-m-d_His').".{$format}";
    }

    /**
     * Generate the report file and return the storage path.
     */
    private function generateFile(
        array $report,
        string $reportType,
        string $format,
        string $filename,
        ?string $businessId,
        ?string $startDate,
        ?string $endDate
    ): string {
        $disk = Storage::disk('local');
        $directory = 'reports/'.now()->format('Y/m');
        $disk->makeDirectory($directory, 0755, true);

        if ($format === 'pdf') {
            return $this->generatePdf($report, $reportType, $filename, $directory, $businessId, $startDate, $endDate);
        } else {
            return $this->generateCsv($report, $reportType, $filename, $directory);
        }
    }

    /**
     * Generate PDF file.
     */
    private function generatePdf(
        array $report,
        string $reportType,
        string $filename,
        string $directory,
        ?string $businessId,
        ?string $startDate,
        ?string $endDate
    ): string {
        $selectedBusiness = $businessId ? Business::find($businessId) : null;

        $pdf = PDF::loadView('reports.pdf', [
            'report' => $report,
            'reportType' => $reportType,
            'business' => $selectedBusiness,
            'startDate' => $startDate,
            'endDate' => $endDate,
        ]);

        $filePath = $directory.'/'.$filename;
        Storage::disk('local')->put($filePath, $pdf->output());

        return $filePath;
    }

    /**
     * Generate CSV file.
     */
    private function generateCsv(array $report, string $reportType, string $filename, string $directory): string
    {
        $filePath = $directory.'/'.$filename;
        $fullPath = Storage::disk('local')->path($filePath);

        $output = fopen($fullPath, 'w');

        // Add BOM for Excel compatibility
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

        $reportController = app(\App\Http\Controllers\ReportController::class);
        $reportController->writeCsvData($output, $report, $reportType);

        fclose($output);

        return $filePath;
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Report generation job permanently failed', [
            'report_generation_id' => $this->reportGeneration->id,
            'exception' => $exception->getMessage(),
        ]);

        $this->reportGeneration->markAsFailed($exception->getMessage());
    }
}
