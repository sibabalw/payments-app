<?php

namespace App\Services;

use App\Helpers\LogContext;
use App\Models\Business;
use App\Models\FinancialLedger;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Compensation Service
 *
 * Handles compensation transactions for partial failures or corrections.
 * Compensation transactions are different from reversals - they represent
 * additional transactions to correct or compensate for issues.
 */
class CompensationService
{
    protected FinancialLedgerService $ledgerService;

    public function __construct(
        ?FinancialLedgerService $ledgerService = null
    ) {
        $this->ledgerService = $ledgerService ?? app(FinancialLedgerService::class);
    }

    /**
     * Create a compensation transaction linked to an original transaction
     *
     * @param  FinancialLedger  $originalEntry  Original ledger entry to compensate
     * @param  float  $amount  Compensation amount (can be different from original)
     * @param  string  $reason  Reason for compensation
     * @param  User|null  $user  User initiating the compensation
     * @param  string|null  $compensationChainId  Chain ID linking original -> reversal -> compensation
     * @param  array|null  $metadata  Additional metadata
     * @return array Array with compensation 'debit' and 'credit' entries
     */
    public function createCompensation(
        FinancialLedger $originalEntry,
        float $amount,
        string $reason,
        ?User $user = null,
        ?string $compensationChainId = null,
        ?array $metadata = null
    ): array {
        // Find the paired entry (debit/credit pair)
        $pairedEntry = FinancialLedger::where('correlation_id', $originalEntry->correlation_id)
            ->where('id', '!=', $originalEntry->id)
            ->first();

        if (! $pairedEntry) {
            throw new \RuntimeException('Cannot create compensation: paired entry not found');
        }

        $correlationId = $this->ledgerService->generateCorrelationId();
        $chainId = $compensationChainId ?? $originalEntry->correlation_id;

        return DB::transaction(function () use ($originalEntry, $pairedEntry, $amount, $reason, $user, $chainId, $correlationId, $metadata) {
            // Validate currency consistency
            if ($originalEntry->currency !== $pairedEntry->currency) {
                throw new \RuntimeException('Cannot create compensation: currency mismatch between entries');
            }

            $currency = $originalEntry->currency;

            // Determine compensation accounts (same as original or different based on reason)
            $debitAccount = $originalEntry->isDebit() ? $originalEntry->account_type : $pairedEntry->account_type;
            $creditAccount = $originalEntry->isCredit() ? $originalEntry->account_type : $pairedEntry->account_type;

            // Get business from original entry
            $business = Business::find($originalEntry->business_id);
            if (! $business) {
                throw new \RuntimeException('Cannot create compensation: business not found');
            }

            // Create compensation metadata
            $compensationMetadata = array_merge($metadata ?? [], [
                'compensation_reason' => $reason,
                'original_correlation_id' => $originalEntry->correlation_id,
                'compensation_chain_id' => $chainId,
                'original_amount' => $originalEntry->amount,
                'compensation_amount' => $amount,
                'compensated_by_user_id' => $user?->id,
                'compensated_at' => now()->toIso8601String(),
            ]);

            // Record compensation transaction
            $result = $this->ledgerService->recordTransaction(
                $correlationId,
                $debitAccount,
                $creditAccount,
                $amount,
                $business,
                "Compensation: {$reason}",
                $originalEntry->reference_type ? $originalEntry->reference : null,
                $compensationMetadata,
                $user,
                $currency,
                'COMPENSATION'
            );

            // Update metadata on both entries to include compensation chain
            $result['debit']->update([
                'metadata' => array_merge($result['debit']->metadata ?? [], [
                    'compensation_chain_id' => $chainId,
                    'is_compensation' => true,
                ]),
            ]);

            $result['credit']->update([
                'metadata' => array_merge($result['credit']->metadata ?? [], [
                    'compensation_chain_id' => $chainId,
                    'is_compensation' => true,
                ]),
            ]);

            LogContext::info('Compensation transaction created', LogContext::create(
                $correlationId,
                $business->id,
                null,
                'compensation',
                $user?->id,
                [
                    'original_correlation_id' => $originalEntry->correlation_id,
                    'compensation_chain_id' => $chainId,
                    'amount' => $amount,
                    'reason' => $reason,
                ]
            ));

            return array_merge($result, [
                'compensation_chain_id' => $chainId,
            ]);
        });
    }

    /**
     * Get compensation chain for a transaction
     *
     * @param  string  $chainId  Compensation chain ID
     * @return array Array with 'original', 'reversal', 'compensation' entries
     */
    public function getCompensationChain(string $chainId): array
    {
        $entries = FinancialLedger::where(function ($query) use ($chainId) {
            $query->where('correlation_id', $chainId)
                ->orWhereJsonContains('metadata->compensation_chain_id', $chainId)
                ->orWhereJsonContains('metadata->original_correlation_id', $chainId);
        })
            ->orderBy('sequence_number')
            ->get();

        $original = $entries->firstWhere('correlation_id', $chainId);
        $reversal = $entries->firstWhere('reversal_of_id', $original?->id);
        $compensation = $entries->firstWhere(fn ($entry) => ($entry->metadata['is_compensation'] ?? false) === true);

        return [
            'original' => $original,
            'reversal' => $reversal,
            'compensation' => $compensation,
            'all_entries' => $entries,
        ];
    }

    /**
     * Check if a transaction has been compensated
     *
     * @param  FinancialLedger  $entry  Ledger entry to check
     * @return bool True if compensated
     */
    public function isCompensated(FinancialLedger $entry): bool
    {
        $chainId = $entry->correlation_id;
        $compensation = FinancialLedger::whereJsonContains('metadata->compensation_chain_id', $chainId)
            ->whereJsonContains('metadata->is_compensation', true)
            ->exists();

        return $compensation;
    }
}
