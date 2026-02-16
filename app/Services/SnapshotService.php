<?php

namespace App\Services;

use App\Models\BalanceSnapshot;
use App\Models\Business;
use App\Models\FinancialLedger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SnapshotService
{
    protected FinancialLedgerService $ledgerService;

    public function __construct(?FinancialLedgerService $ledgerService = null)
    {
        $this->ledgerService = $ledgerService ?? app(FinancialLedgerService::class);
    }

    /**
     * Create snapshot for an account
     */
    public function createSnapshot(Business $business, string $accountType, \DateTime $snapshotDate): BalanceSnapshot
    {
        return DB::transaction(function () use ($business, $accountType, $snapshotDate) {
            // Get all entries up to snapshot date
            $entries = FinancialLedger::where('business_id', $business->id)
                ->where('account_type', $accountType)
                ->where('posting_state', FinancialLedger::POSTING_POSTED)
                ->whereDate('effective_at', '<=', $snapshotDate)
                ->whereNull('reversal_of_id')
                ->orderBy('sequence_number', 'asc')
                ->get();

            // Calculate balance
            $debits = $entries->where('transaction_type', FinancialLedgerService::TYPE_DEBIT)->sum('amount_minor_units');
            $credits = $entries->where('transaction_type', FinancialLedgerService::TYPE_CREDIT)->sum('amount_minor_units');
            $balanceMinorUnits = (int) ($debits - $credits);

            // Get max sequence number
            $maxSequence = $entries->max('sequence_number') ?? 0;

            // Calculate checksum (SHA256 of all entry data)
            $checksumData = $entries->map(function ($entry) {
                return "{$entry->id}:{$entry->sequence_number}:{$entry->transaction_type}:{$entry->amount_minor_units}";
            })->join('|');

            $checksum = hash('sha256', $checksumData);

            // Create or update snapshot
            $snapshot = BalanceSnapshot::updateOrCreate(
                [
                    'account_type' => $accountType,
                    'business_id' => $business->id,
                    'snapshot_date' => $snapshotDate->format('Y-m-d'),
                ],
                [
                    'balance_minor_units' => $balanceMinorUnits,
                    'sequence_number' => $maxSequence,
                    'checksum' => $checksum,
                    'entry_count' => $entries->count(),
                ]
            );

            Log::info('Balance snapshot created', [
                'business_id' => $business->id,
                'account_type' => $accountType,
                'snapshot_date' => $snapshotDate->format('Y-m-d'),
                'balance_minor_units' => $balanceMinorUnits,
                'sequence_number' => $maxSequence,
                'entry_count' => $entries->count(),
            ]);

            return $snapshot;
        });
    }

    /**
     * Get balance using snapshot optimization
     */
    public function getBalanceFromSnapshot(Business $business, string $accountType): float
    {
        // Get latest snapshot
        $snapshot = BalanceSnapshot::where('business_id', $business->id)
            ->where('account_type', $accountType)
            ->orderBy('snapshot_date', 'desc')
            ->first();

        if (! $snapshot) {
            // No snapshot - calculate from all entries
            return $this->ledgerService->getAccountBalance($business, $accountType, true);
        }

        // Get entries after snapshot
        $newEntries = FinancialLedger::where('business_id', $business->id)
            ->where('account_type', $accountType)
            ->where('posting_state', FinancialLedger::POSTING_POSTED)
            ->whereNull('reversal_of_id')
            ->where('sequence_number', '>', $snapshot->sequence_number)
            ->get();

        // Calculate balance from snapshot + new entries
        $snapshotBalance = $this->ledgerService->fromMinorUnits($snapshot->balance_minor_units);
        $newDebits = $newEntries->where('transaction_type', FinancialLedgerService::TYPE_DEBIT)->sum('amount');
        $newCredits = $newEntries->where('transaction_type', FinancialLedgerService::TYPE_CREDIT)->sum('amount');
        $newBalance = $newDebits - $newCredits;

        return $snapshotBalance + $newBalance;
    }

    /**
     * Verify snapshot checksum
     */
    public function verifySnapshot(BalanceSnapshot $snapshot): bool
    {
        $entries = FinancialLedger::where('business_id', $snapshot->business_id)
            ->where('account_type', $snapshot->account_type)
            ->where('posting_state', FinancialLedger::POSTING_POSTED)
            ->where('sequence_number', '<=', $snapshot->sequence_number)
            ->whereNull('reversal_of_id')
            ->orderBy('sequence_number', 'asc')
            ->get();

        $checksumData = $entries->map(function ($entry) {
            return "{$entry->id}:{$entry->sequence_number}:{$entry->transaction_type}:{$entry->amount_minor_units}";
        })->join('|');

        $calculatedChecksum = hash('sha256', $checksumData);

        return $calculatedChecksum === $snapshot->checksum;
    }
}
