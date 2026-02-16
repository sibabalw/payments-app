<?php

namespace App\Services;

use App\Models\Business;
use Illuminate\Support\Facades\Log;

class ReconciliationService
{
    protected EscrowService $escrowService;

    protected FinancialLedgerService $ledgerService;

    public function __construct(
        ?EscrowService $escrowService = null,
        ?FinancialLedgerService $ledgerService = null
    ) {
        $this->escrowService = $escrowService ?? app(EscrowService::class);
        $this->ledgerService = $ledgerService ?? app(FinancialLedgerService::class);
    }

    /**
     * Reconcile balance for a business
     *
     * CRITICAL: Only rounding drift (<0.01) is auto-fixed. Everything else requires manual approval
     * and creates a ReconciliationDiscrepancy record. Account is frozen until resolved.
     */
    public function reconcileBalance(Business $business, bool $autoFix = false): array
    {
        $storedBalance = $this->escrowService->getAvailableBalance($business, false, false);
        $calculatedBalance = $this->escrowService->recalculateBalance($business);
        $ledgerBalance = $this->ledgerService->getAccountBalance($business, FinancialLedgerService::ACCOUNT_ESCROW);

        $discrepancies = [];
        $roundingThreshold = config('regulatory.auto_fix_rounding_threshold', 0.01);

        // Compare stored vs calculated
        $storedVsCalculatedDiff = abs($storedBalance - $calculatedBalance);
        if ($storedVsCalculatedDiff > $roundingThreshold) {
            $discrepancies[] = [
                'type' => 'stored_vs_calculated',
                'stored' => $storedBalance,
                'calculated' => $calculatedBalance,
                'difference' => $storedVsCalculatedDiff,
            ];
        }

        // Compare stored vs ledger (ledger is source of truth)
        $storedVsLedgerDiff = abs($storedBalance - $ledgerBalance);
        if ($storedVsLedgerDiff > $roundingThreshold) {
            $discrepancies[] = [
                'type' => 'stored_vs_ledger',
                'stored' => $storedBalance,
                'ledger' => $ledgerBalance,
                'difference' => $storedVsLedgerDiff,
            ];
        }

        // Auto-fix ONLY rounding drift (< threshold)
        if ($autoFix && ! empty($discrepancies)) {
            foreach ($discrepancies as $discrepancy) {
                $diff = $discrepancy['difference'];

                // Only auto-fix rounding drift
                if ($diff <= $roundingThreshold) {
                    // Rounding drift - safe to auto-fix
                    $this->escrowService->rebuildBalanceFromLedger($business);
                    Log::info('Balance auto-fixed (rounding drift only)', [
                        'business_id' => $business->id,
                        'difference' => $diff,
                        'threshold' => $roundingThreshold,
                    ]);
                } else {
                    // Significant discrepancy - requires manual approval
                    $this->createDiscrepancy($business, $discrepancy);
                    // Only freeze if discrepancy is very large (> 1.00)
                    if ($diff > 1.00) {
                        $this->freezeAccount($business);
                    }
                    Log::warning('Significant balance discrepancy detected', [
                        'business_id' => $business->id,
                        'discrepancy' => $discrepancy,
                        'difference' => $diff,
                        'threshold' => $roundingThreshold,
                    ]);
                }
            }
        } elseif (! empty($discrepancies)) {
            // Even without autoFix, create discrepancies for tracking
            foreach ($discrepancies as $discrepancy) {
                if ($discrepancy['difference'] > $roundingThreshold) {
                    $this->createDiscrepancy($business, $discrepancy);
                }
            }
        }

        return [
            'business_id' => $business->id,
            'stored_balance' => $storedBalance,
            'calculated_balance' => $calculatedBalance,
            'ledger_balance' => $ledgerBalance,
            'discrepancies' => $discrepancies,
            'reconciled' => empty($discrepancies),
        ];
    }

    /**
     * Create reconciliation discrepancy record
     */
    protected function createDiscrepancy(Business $business, array $discrepancy): void
    {
        \App\Models\ReconciliationDiscrepancy::create([
            'business_id' => $business->id,
            'discrepancy_type' => $discrepancy['type'],
            'stored_balance' => $discrepancy['stored'],
            'calculated_balance' => $discrepancy['calculated'] ?? null,
            'ledger_balance' => $discrepancy['ledger'] ?? null,
            'difference' => $discrepancy['difference'],
            'status' => 'pending',
            'metadata' => [
                'detected_at' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * Freeze account (prevent transactions)
     */
    protected function freezeAccount(Business $business): void
    {
        if (! $business->is_frozen) {
            $business->update(['is_frozen' => true]);
            Log::warning('Business account frozen due to reconciliation discrepancy', [
                'business_id' => $business->id,
            ]);
        }
    }

    /**
     * Reconcile all businesses
     */
    public function reconcileAll(bool $autoFix = false): array
    {
        $businesses = Business::all();
        $results = [];

        foreach ($businesses as $business) {
            $results[] = $this->reconcileBalance($business, $autoFix);
        }

        return $results;
    }

    /**
     * Verify ledger balances are balanced (double-entry check)
     */
    public function verifyLedgerBalances(?Business $business = null): array
    {
        return $this->ledgerService->verifyBalances($business);
    }
}
