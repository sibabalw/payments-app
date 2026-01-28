<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BillingTransaction;
use App\Models\MonthlyBilling;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SubscriptionController extends Controller
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * Display subscriptions management page.
     */
    public function index(): Response
    {
        // Get all subscriptions with business info
        $subscriptions = MonthlyBilling::query()
            ->with('business:id,name,status')
            ->orderByDesc('billing_month')
            ->orderByDesc('created_at')
            ->paginate(50);

        // Subscription statistics
        $stats = MonthlyBilling::query()
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN status = "pending" THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = "paid" THEN 1 ELSE 0 END) as paid,
                SUM(CASE WHEN status = "waived" THEN 1 ELSE 0 END) as waived,
                SUM(CASE WHEN status = "paid" THEN subscription_fee ELSE 0 END) as total_revenue,
                SUM(subscription_fee) as total_billed
            ')
            ->first();

        // Monthly revenue trends (last 6 months)
        $revenueTrends = MonthlyBilling::query()
            ->where('billing_month', '>=', now()->subMonths(6)->format('Y-m'))
            ->select(
                'billing_month',
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(CASE WHEN status = "paid" THEN subscription_fee ELSE 0 END) as revenue'),
                DB::raw('SUM(subscription_fee) as total_billed')
            )
            ->groupBy('billing_month')
            ->orderBy('billing_month')
            ->get()
            ->map(fn ($item) => [
                'month' => $item->billing_month,
                'count' => (int) $item->count,
                'revenue' => (float) $item->revenue,
                'total_billed' => (float) $item->total_billed,
            ]);

        // Recent billing transactions
        $recentTransactions = BillingTransaction::query()
            ->with('business:id,name')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->map(fn ($transaction) => [
                'id' => $transaction->id,
                'business_id' => $transaction->business_id,
                'business_name' => $transaction->business->name ?? 'Unknown',
                'type' => $transaction->type,
                'amount' => (float) $transaction->amount,
                'status' => $transaction->status,
                'created_at' => $transaction->created_at->toIso8601String(),
            ]);

        return Inertia::render('admin/subscriptions/index', [
            'subscriptions' => $subscriptions,
            'stats' => [
                'total' => (int) ($stats->total ?? 0),
                'pending' => (int) ($stats->pending ?? 0),
                'paid' => (int) ($stats->paid ?? 0),
                'waived' => (int) ($stats->waived ?? 0),
                'total_revenue' => (float) ($stats->total_revenue ?? 0),
                'total_billed' => (float) ($stats->total_billed ?? 0),
            ],
            'revenueTrends' => $revenueTrends,
            'recentTransactions' => $recentTransactions,
        ]);
    }

    /**
     * Update subscription status.
     */
    public function updateStatus(Request $request, MonthlyBilling $billing): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:pending,paid,waived'],
        ]);

        $oldStatus = $billing->status;

        $billing->update([
            'status' => $validated['status'],
            'paid_at' => $validated['status'] === 'paid' ? now() : null,
        ]);

        $this->auditService->log('admin.subscription.status_updated', $billing, null, null, null, null, null, [
            'old_status' => $oldStatus,
            'new_status' => $validated['status'],
            'business_id' => $billing->business_id,
        ]);

        return back()->with('success', 'Subscription status updated successfully.');
    }
}
