<?php

namespace App\Jobs;

use App\Models\Business;
use App\Services\EscrowService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ReconcileEscrowBalances implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The maximum number of seconds a job can run before timing out.
     */
    public int $timeout = 600; // 10 minutes

    /**
     * Execute the job.
     */
    public function handle(EscrowService $escrowService): void
    {
        Log::info('Starting escrow balance reconciliation');

        // Process businesses in chunks to avoid memory issues
        $totalBusinesses = 0;
        $driftDetected = 0;
        $driftFixed = 0;
        $errors = 0;
        $maxDrift = 0.0;
        $totalDrift = 0.0;

        Business::whereNotNull('escrow_balance')
            ->orWhereHas('escrowDeposits')
            ->orWhereHas('paymentSchedules.paymentJobs')
            ->orWhereHas('payrollSchedules.payrollJobs')
            ->chunk(100, function ($businesses) use ($escrowService, &$totalBusinesses, &$driftDetected, &$driftFixed, &$errors, &$maxDrift, &$totalDrift) {
                foreach ($businesses as $business) {
                    try {
                        $totalBusinesses++;

                        // Calculate actual balance from scratch
                        $calculatedBalance = $escrowService->recalculateBalance($business);

                        // Get stored balance
                        $storedBalance = (float) ($business->escrow_balance ?? 0);

                        // Calculate drift
                        $drift = abs($calculatedBalance - $storedBalance);

                        if ($drift > 0.01) { // Only flag if drift is more than 1 cent
                            $driftDetected++;

                            // Track maximum drift
                            if ($drift > $maxDrift) {
                                $maxDrift = $drift;
                            }

                            $totalDrift += $drift;

                            Log::warning('Escrow balance drift detected', [
                                'business_id' => $business->id,
                                'business_name' => $business->name,
                                'stored_balance' => $storedBalance,
                                'calculated_balance' => $calculatedBalance,
                                'drift' => $drift,
                            ]);

                            // Balance was already corrected by recalculateBalance()
                            $driftFixed++;
                        } elseif ($drift > 0.0) {
                            // Small drift (less than 1 cent) - log but don't flag
                            Log::debug('Minor escrow balance drift (within tolerance)', [
                                'business_id' => $business->id,
                                'stored_balance' => $storedBalance,
                                'calculated_balance' => $calculatedBalance,
                                'drift' => $drift,
                            ]);
                        }
                    } catch (\Exception $e) {
                        $errors++;
                        Log::error('Failed to reconcile escrow balance for business', [
                            'business_id' => $business->id,
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                        ]);
                    }
                }
            });

        Log::info('Escrow balance reconciliation completed', [
            'total_businesses' => $totalBusinesses,
            'drift_detected' => $driftDetected,
            'drift_fixed' => $driftFixed,
            'errors' => $errors,
            'max_drift' => $maxDrift,
            'total_drift' => $totalDrift,
            'average_drift' => $driftDetected > 0 ? ($totalDrift / $driftDetected) : 0,
        ]);

        // Alert if significant drift detected
        if ($driftDetected > 0 && $maxDrift > 10.0) {
            Log::critical('Significant escrow balance drift detected during reconciliation', [
                'businesses_affected' => $driftDetected,
                'max_drift' => $maxDrift,
                'total_drift' => $totalDrift,
            ]);
        }
    }
}
