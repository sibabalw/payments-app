<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\EscrowDeposit;
use App\Models\PaymentJob;
use App\Services\EscrowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class EscrowController extends Controller
{
    public function __construct(
        protected EscrowService $escrowService
    ) {
    }

    /**
     * Display escrow management dashboard.
     */
    public function index(): Response
    {
        $pendingDeposits = EscrowDeposit::where('status', 'pending')
            ->with(['business', 'enteredBy'])
            ->orderBy('created_at', 'desc')
            ->get();

        $confirmedDeposits = EscrowDeposit::where('status', 'confirmed')
            ->with(['business', 'enteredBy'])
            ->orderBy('completed_at', 'desc')
            ->limit(20)
            ->get();

        // Get businesses with their balances
        $businesses = Business::with('escrowDeposits')->get()->map(function ($business) {
            return [
                'id' => $business->id,
                'name' => $business->name,
                'balance' => $this->escrowService->getAvailableBalance($business),
            ];
        });

        // Get payment jobs that need fee release or fund return recording
        $succeededPayments = PaymentJob::where('status', 'succeeded')
            ->whereNull('fee_released_manually_at')
            ->whereNotNull('escrow_deposit_id')
            ->with(['paymentSchedule.business', 'receiver', 'escrowDeposit'])
            ->orderBy('processed_at', 'desc')
            ->limit(20)
            ->get();

        $failedPayments = PaymentJob::where('status', 'failed')
            ->whereNull('funds_returned_manually_at')
            ->whereNotNull('escrow_deposit_id')
            ->with(['paymentSchedule.business', 'receiver', 'escrowDeposit'])
            ->orderBy('processed_at', 'desc')
            ->limit(20)
            ->get();

        return Inertia::render('admin/escrow/index', [
            'pendingDeposits' => $pendingDeposits,
            'confirmedDeposits' => $confirmedDeposits,
            'businesses' => $businesses,
            'escrowAccountNumber' => config('escrow.account_number', ''),
            'succeededPayments' => $succeededPayments,
            'failedPayments' => $failedPayments,
        ]);
    }

    /**
     * Manually record a deposit.
     */
    public function createDeposit(Request $request)
    {
        $validated = $request->validate([
            'business_id' => 'required|exists:businesses,id',
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'required|string|size:3',
            'bank_reference' => 'nullable|string|max:255',
        ]);

        $business = Business::findOrFail($validated['business_id']);

        try {
            $deposit = $this->escrowService->recordManualDeposit(
                $business,
                $validated['amount'],
                $validated['currency'],
                $validated['bank_reference'] ?? null,
                Auth::user()
            );

            return redirect()->route('admin.escrow.index')
                ->with('success', 'Deposit recorded successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['amount' => $e->getMessage()])->withInput();
        }
    }

    /**
     * Confirm a pending deposit.
     */
    public function confirmDeposit(Request $request, EscrowDeposit $deposit)
    {
        $validated = $request->validate([
            'bank_reference' => 'nullable|string|max:255',
        ]);

        try {
            $this->escrowService->confirmDeposit(
                $deposit,
                $validated['bank_reference'] ?? null,
                Auth::user()
            );

            return redirect()->route('admin.escrow.index')
                ->with('success', 'Deposit confirmed successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Record fee release for a payment.
     */
    public function recordFeeRelease(Request $request, PaymentJob $paymentJob)
    {
        try {
            $this->escrowService->recordFeeRelease($paymentJob, Auth::user());

            return redirect()->back()
                ->with('success', 'Fee release recorded successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Record fund return for a payment.
     */
    public function recordFundReturn(Request $request, PaymentJob $paymentJob)
    {
        try {
            $this->escrowService->recordFundReturn($paymentJob, Auth::user());

            return redirect()->back()
                ->with('success', 'Fund return recorded successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * View balance for all businesses.
     */
    public function viewBalances(): Response
    {
        $businesses = Business::all()->map(function ($business) {
            $deposits = $business->escrowDeposits()
                ->where('status', 'confirmed')
                ->get();

            $totalDeposited = $deposits->sum('amount');
            $totalFees = $deposits->sum('fee_amount');
            $totalAuthorized = $deposits->sum('authorized_amount');

            $used = PaymentJob::whereHas('paymentSchedule', function ($query) use ($business) {
                $query->where('business_id', $business->id);
            })
            ->whereNotNull('escrow_deposit_id')
            ->whereIn('status', ['succeeded', 'processing'])
            ->sum('amount');

            $returned = PaymentJob::whereHas('paymentSchedule', function ($query) use ($business) {
                $query->where('business_id', $business->id);
            })
            ->whereNotNull('funds_returned_manually_at')
            ->sum('amount');

            return [
                'id' => $business->id,
                'name' => $business->name,
                'total_deposited' => $totalDeposited,
                'total_fees' => $totalFees,
                'total_authorized' => $totalAuthorized,
                'used' => $used,
                'returned' => $returned,
                'available_balance' => $this->escrowService->getAvailableBalance($business),
            ];
        });

        return Inertia::render('admin/escrow/balances', [
            'businesses' => $businesses,
        ]);
    }
}
