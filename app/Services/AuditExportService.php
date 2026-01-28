<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\AuditPack;
use App\Models\Business;
use App\Models\FinancialLedger;
use App\Models\TransactionReversal;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use ZipArchive;

class AuditExportService
{
    /**
     * Export audit pack for a business and date range
     */
    public function exportAuditPack(
        ?Business $business,
        \DateTime $dateFrom,
        \DateTime $dateTo,
        ?\App\Models\User $exportedBy = null
    ): string {
        $tempDir = storage_path('app/temp/audit_packs');
        if (! File::exists($tempDir)) {
            File::makeDirectory($tempDir, 0755, true);
        }

        $packId = uniqid('audit_pack_');
        $packDir = "{$tempDir}/{$packId}";

        File::makeDirectory($packDir, 0755, true);

        try {
            // Export ledger entries
            $ledgerEntries = $this->exportLedgerEntries($business, $dateFrom, $dateTo, $packDir);

            // Export audit logs
            $auditLogs = $this->exportAuditLogs($business, $dateFrom, $dateTo, $packDir);

            // Export reversals
            $reversals = $this->exportReversals($business, $dateFrom, $dateTo, $packDir);

            // Create manifest
            $manifest = $this->createManifest($business, $dateFrom, $dateTo, $ledgerEntries, $auditLogs, $reversals, $packDir);

            // Create signature
            $signature = $this->createSignature($packDir);

            // Create ZIP file
            $zipPath = $this->createZip($packDir, $packId);

            // Calculate final hash
            $packHash = hash_file('sha256', $zipPath);

            // Store audit pack record
            AuditPack::create([
                'business_id' => $business?->id,
                'date_from' => $dateFrom->format('Y-m-d'),
                'date_to' => $dateTo->format('Y-m-d'),
                'pack_filename' => basename($zipPath),
                'pack_hash' => $packHash,
                'ledger_entry_count' => $ledgerEntries,
                'audit_log_count' => $auditLogs,
                'reversal_count' => $reversals,
                'exported_by' => $exportedBy?->id,
                'exported_at' => now(),
                'metadata' => [
                    'exported_at' => now()->toIso8601String(),
                ],
            ]);

            // Cleanup temp directory
            File::deleteDirectory($packDir);

            Log::info('Audit pack exported', [
                'business_id' => $business?->id,
                'date_from' => $dateFrom->format('Y-m-d'),
                'date_to' => $dateTo->format('Y-m-d'),
                'pack_hash' => $packHash,
            ]);

            return $zipPath;
        } catch (\Exception $e) {
            // Cleanup on error
            if (File::exists($packDir)) {
                File::deleteDirectory($packDir);
            }

            throw $e;
        }
    }

    /**
     * Export ledger entries to CSV
     */
    protected function exportLedgerEntries(?Business $business, \DateTime $dateFrom, \DateTime $dateTo, string $packDir): int
    {
        $query = FinancialLedger::whereBetween('effective_at', [$dateFrom, $dateTo]);

        if ($business) {
            $query->where('business_id', $business->id);
        }

        $entries = $query->orderBy('sequence_number')->get();

        $csvPath = "{$packDir}/ledger_entries.csv";
        $handle = fopen($csvPath, 'w');

        // Header
        fputcsv($handle, [
            'id', 'correlation_id', 'sequence_number', 'transaction_type', 'account_type',
            'business_id', 'amount', 'amount_minor_units', 'currency', 'description',
            'posting_state', 'effective_at', 'created_at',
        ]);

        // Data
        foreach ($entries as $entry) {
            fputcsv($handle, [
                $entry->id,
                $entry->correlation_id,
                $entry->sequence_number,
                $entry->transaction_type,
                $entry->account_type,
                $entry->business_id,
                $entry->amount,
                $entry->amount_minor_units,
                $entry->currency,
                $entry->description,
                $entry->posting_state,
                $entry->effective_at?->format('Y-m-d H:i:s'),
                $entry->created_at->format('Y-m-d H:i:s'),
            ]);
        }

        fclose($handle);

        return $entries->count();
    }

    /**
     * Export audit logs to CSV
     */
    protected function exportAuditLogs(?Business $business, \DateTime $dateFrom, \DateTime $dateTo, string $packDir): int
    {
        $query = AuditLog::whereBetween('created_at', [$dateFrom, $dateTo]);

        if ($business) {
            $query->where('business_id', $business->id);
        }

        $logs = $query->orderBy('created_at')->get();

        $csvPath = "{$packDir}/audit_logs.csv";
        $handle = fopen($csvPath, 'w');

        fputcsv($handle, [
            'id', 'user_id', 'business_id', 'action', 'model_type', 'model_id',
            'correlation_id', 'created_at',
        ]);

        foreach ($logs as $log) {
            fputcsv($handle, [
                $log->id,
                $log->user_id,
                $log->business_id,
                $log->action,
                $log->model_type,
                $log->model_id,
                $log->correlation_id,
                $log->created_at->format('Y-m-d H:i:s'),
            ]);
        }

        fclose($handle);

        return $logs->count();
    }

    /**
     * Export reversals to CSV
     */
    protected function exportReversals(?Business $business, \DateTime $dateFrom, \DateTime $dateTo, string $packDir): int
    {
        $query = TransactionReversal::whereBetween('created_at', [$dateFrom, $dateTo]);

        if ($business) {
            $query->whereHas('reversible', function ($q) use ($business) {
                if (method_exists($q->getModel(), 'business')) {
                    $q->where('business_id', $business->id);
                }
            });
        }

        $reversals = $query->orderBy('created_at')->get();

        $csvPath = "{$packDir}/reversals.csv";
        $handle = fopen($csvPath, 'w');

        fputcsv($handle, [
            'id', 'reversible_type', 'reversible_id', 'reversal_type', 'reason',
            'status', 'reversed_at', 'created_at',
        ]);

        foreach ($reversals as $reversal) {
            fputcsv($handle, [
                $reversal->id,
                $reversal->reversible_type,
                $reversal->reversible_id,
                $reversal->reversal_type,
                $reversal->reason,
                $reversal->status,
                $reversal->reversed_at?->format('Y-m-d H:i:s'),
                $reversal->created_at->format('Y-m-d H:i:s'),
            ]);
        }

        fclose($handle);

        return $reversals->count();
    }

    /**
     * Create manifest file
     */
    protected function createManifest(
        ?Business $business,
        \DateTime $dateFrom,
        \DateTime $dateTo,
        int $ledgerCount,
        int $auditCount,
        int $reversalCount,
        string $packDir
    ): array {
        $manifest = [
            'business_id' => $business?->id,
            'date_from' => $dateFrom->format('Y-m-d'),
            'date_to' => $dateTo->format('Y-m-d'),
            'exported_at' => now()->toIso8601String(),
            'counts' => [
                'ledger_entries' => $ledgerCount,
                'audit_logs' => $auditCount,
                'reversals' => $reversalCount,
            ],
            'files' => [
                'ledger_entries.csv',
                'audit_logs.csv',
                'reversals.csv',
            ],
        ];

        // Calculate file hashes
        foreach ($manifest['files'] as $file) {
            $filePath = "{$packDir}/{$file}";
            if (File::exists($filePath)) {
                $manifest['file_hashes'][$file] = hash_file('sha256', $filePath);
            }
        }

        $manifestPath = "{$packDir}/manifest.json";
        File::put($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT));

        return $manifest;
    }

    /**
     * Create signature file (SHA256 of all files)
     */
    protected function createSignature(string $packDir): string
    {
        $files = ['ledger_entries.csv', 'audit_logs.csv', 'reversals.csv', 'manifest.json'];
        $hashes = [];

        foreach ($files as $file) {
            $filePath = "{$packDir}/{$file}";
            if (File::exists($filePath)) {
                $hashes[$file] = hash_file('sha256', $filePath);
            }
        }

        $signature = hash('sha256', json_encode($hashes));
        File::put("{$packDir}/signature.txt", $signature);

        return $signature;
    }

    /**
     * Create ZIP archive
     */
    protected function createZip(string $packDir, string $packId): string
    {
        $zipPath = storage_path("app/audit_packs/{$packId}.zip");
        $zipDir = dirname($zipPath);

        if (! File::exists($zipDir)) {
            File::makeDirectory($zipDir, 0755, true);
        }

        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
            $files = File::allFiles($packDir);
            foreach ($files as $file) {
                $zip->addFile($file->getPathname(), $file->getFilename());
            }
            $zip->close();
        }

        return $zipPath;
    }
}
