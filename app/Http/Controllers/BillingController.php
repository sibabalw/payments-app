<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Services\BillingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class BillingController extends Controller
{
    public function __construct(
        protected BillingService $billingService
    ) {
    }

    /**
     * Display the billing dashboard.
     */
    public function index(): Response
    {
        $businessId = Auth::user()->current_business_id ?? session('current_business_id');
        $user = Auth::user();

        $businesses = $user->businesses()->get();
        
        if (!$businessId && $businesses->isNotEmpty()) {
            $businessId = $businesses->first()->id;
        }

        $business = $businessId ? Business::find($businessId) : null;

        if (!$business) {
            return Inertia::render('billing/index', [
                'businesses' => $businesses,
                'selectedBusinessId' => null,
                'currentMonthBilling' => null,
                'billingHistory' => collect(),
            ]);
        }

        // Get current month billing
        $currentMonth = now()->format('Y-m');
        $currentMonthBilling = $business->monthlyBillings()
            ->where('billing_month', $currentMonth)
            ->first();

        if (!$currentMonthBilling) {
            // Generate if doesn't exist
            $currentMonthBilling = $this->billingService->generateMonthlyBilling($business, $currentMonth);
        }

        // Get billing history
        $billingHistory = $business->monthlyBillings()
            ->orderBy('billing_month', 'desc')
            ->limit(12)
            ->get();

        // Get current month deposit fees
        $startDate = $currentMonth . '-01';
        $endDate = date('Y-m-t', strtotime($startDate));
        $currentMonthDepositFees = $business->escrowDeposits()
            ->where('status', 'completed')
            ->whereBetween('completed_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->sum('fee_amount');

        return Inertia::render('billing/index', [
            'businesses' => $businesses,
            'selectedBusinessId' => $businessId,
            'business' => $business,
            'currentMonthBilling' => $currentMonthBilling,
            'currentMonthDepositFees' => (float) $currentMonthDepositFees,
            'billingHistory' => $billingHistory,
            'subscriptionFee' => $this->billingService->getSubscriptionFee($business),
        ]);
    }

    /**
     * Show billing details for a specific month.
     */
    public function show($id): Response
    {
        $billing = \App\Models\MonthlyBilling::with(['business', 'billingTransactions'])
            ->findOrFail($id);

        // Verify user has access
        if (!Auth::user()->businesses()->where('businesses.id', $billing->business_id)->exists()) {
            abort(403);
        }

        return Inertia::render('billing/show', [
            'billing' => $billing,
        ]);
    }
}
