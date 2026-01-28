<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Business;
use App\Models\EscrowDeposit;
use App\Models\PaymentJob;
use App\Models\PayrollJob;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SystemReportsController extends Controller
{
    /**
     * Display system reports page.
     */
    public function index(): Response
    {
        // Business activity report (last 30 days)
        $businessActivity = $this->getBusinessActivity();

        // Transaction reports (last 30 days)
        $transactionReport = $this->getTransactionReport();

        // Financial reports
        $financialReport = $this->getFinancialReport();

        // User activity report (last 30 days)
        $userActivity = $this->getUserActivity();

        // Summary statistics
        $summary = [
            'total_businesses' => Business::count(),
            'active_businesses' => Business::where('status', 'active')->count(),
            'total_users' => User::count(),
            'verified_users' => User::whereNotNull('email_verified_at')->count(),
            'total_payments' => PaymentJob::where('status', 'succeeded')->count(),
            'total_payroll' => PayrollJob::where('status', 'succeeded')->count(),
            'total_escrow_balance' => EscrowDeposit::where('status', 'confirmed')->sum('authorized_amount'),
        ];

        return Inertia::render('admin/system-reports/index', [
            'businessActivity' => $businessActivity,
            'transactionReport' => $transactionReport,
            'financialReport' => $financialReport,
            'userActivity' => $userActivity,
            'summary' => $summary,
        ]);
    }

    /**
     * Get business activity report.
     */
    private function getBusinessActivity(): array
    {
        $last30Days = now()->subDays(30);

        // New businesses
        $newBusinesses = Business::query()
            ->where('created_at', '>=', $last30Days)
            ->select(
                DB::raw("DATE_FORMAT(created_at, '%Y-%m-%d') as date"),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn ($item) => [
                'date' => $item->date,
                'count' => (int) $item->count,
            ]);

        // Status changes
        $statusChanges = Business::query()
            ->where('status_changed_at', '>=', $last30Days)
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->get()
            ->map(fn ($item) => [
                'status' => $item->status,
                'count' => (int) $item->count,
            ]);

        // Businesses by status
        $byStatus = Business::query()
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->get()
            ->map(fn ($item) => [
                'status' => $item->status,
                'count' => (int) $item->count,
            ]);

        return [
            'new_businesses' => $newBusinesses,
            'status_changes' => $statusChanges,
            'by_status' => $byStatus,
        ];
    }

    /**
     * Get transaction report.
     */
    private function getTransactionReport(): array
    {
        $last30Days = now()->subDays(30);

        // Payment transactions by day
        $paymentTrends = PaymentJob::query()
            ->where('processed_at', '>=', $last30Days)
            ->select(
                DB::raw("DATE_FORMAT(processed_at, '%Y-%m-%d') as date"),
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(CASE WHEN status = "succeeded" THEN amount ELSE 0 END) as total_amount'),
                DB::raw('SUM(CASE WHEN status = "succeeded" THEN 1 ELSE 0 END) as succeeded'),
                DB::raw('SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) as failed')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn ($item) => [
                'date' => $item->date,
                'count' => (int) $item->count,
                'total_amount' => (float) $item->total_amount,
                'succeeded' => (int) $item->succeeded,
                'failed' => (int) $item->failed,
            ]);

        // Payroll transactions by day
        $payrollTrends = PayrollJob::query()
            ->where('processed_at', '>=', $last30Days)
            ->select(
                DB::raw("DATE_FORMAT(processed_at, '%Y-%m-%d') as date"),
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(CASE WHEN status = "succeeded" THEN gross_salary ELSE 0 END) as total_amount'),
                DB::raw('SUM(CASE WHEN status = "succeeded" THEN 1 ELSE 0 END) as succeeded'),
                DB::raw('SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) as failed')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn ($item) => [
                'date' => $item->date,
                'count' => (int) $item->count,
                'total_amount' => (float) $item->total_amount,
                'succeeded' => (int) $item->succeeded,
                'failed' => (int) $item->failed,
            ]);

        // Summary
        $paymentSummary = PaymentJob::query()
            ->where('processed_at', '>=', $last30Days)
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN status = "succeeded" THEN 1 ELSE 0 END) as succeeded,
                SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) as failed,
                SUM(CASE WHEN status = "succeeded" THEN amount ELSE 0 END) as total_amount
            ')
            ->first();

        $payrollSummary = PayrollJob::query()
            ->where('processed_at', '>=', $last30Days)
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN status = "succeeded" THEN 1 ELSE 0 END) as succeeded,
                SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) as failed,
                SUM(CASE WHEN status = "succeeded" THEN gross_salary ELSE 0 END) as total_amount
            ')
            ->first();

        return [
            'payment_trends' => $paymentTrends,
            'payroll_trends' => $payrollTrends,
            'payment_summary' => [
                'total' => (int) ($paymentSummary->total ?? 0),
                'succeeded' => (int) ($paymentSummary->succeeded ?? 0),
                'failed' => (int) ($paymentSummary->failed ?? 0),
                'total_amount' => (float) ($paymentSummary->total_amount ?? 0),
            ],
            'payroll_summary' => [
                'total' => (int) ($payrollSummary->total ?? 0),
                'succeeded' => (int) ($payrollSummary->succeeded ?? 0),
                'failed' => (int) ($payrollSummary->failed ?? 0),
                'total_amount' => (float) ($payrollSummary->total_amount ?? 0),
            ],
        ];
    }

    /**
     * Get financial report.
     */
    private function getFinancialReport(): array
    {
        // Escrow balances by business
        $escrowBalances = EscrowDeposit::query()
            ->where('status', 'confirmed')
            ->join('businesses', 'escrow_deposits.business_id', '=', 'businesses.id')
            ->select(
                'businesses.id',
                'businesses.name',
                DB::raw('SUM(escrow_deposits.authorized_amount) as total_balance'),
                DB::raw('COUNT(*) as deposit_count')
            )
            ->groupBy('businesses.id', 'businesses.name')
            ->orderByDesc('total_balance')
            ->limit(10)
            ->get()
            ->map(fn ($item) => [
                'business_id' => $item->id,
                'business_name' => $item->name,
                'total_balance' => (float) $item->total_balance,
                'deposit_count' => (int) $item->deposit_count,
            ]);

        // Total escrow balance
        $totalEscrowBalance = EscrowDeposit::where('status', 'confirmed')->sum('authorized_amount');

        // Fees collected (last 30 days)
        $feesCollected = EscrowDeposit::query()
            ->where('status', 'completed')
            ->where('completed_at', '>=', now()->subDays(30))
            ->sum('fee_amount');

        // Monthly fee trends
        $feeTrends = EscrowDeposit::query()
            ->where('status', 'completed')
            ->where('completed_at', '>=', now()->subMonths(6))
            ->select(
                DB::raw("DATE_FORMAT(completed_at, '%Y-%m') as month"),
                DB::raw('SUM(fee_amount) as total_fees'),
                DB::raw('COUNT(*) as transaction_count')
            )
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->map(fn ($item) => [
                'month' => $item->month,
                'total_fees' => (float) $item->total_fees,
                'transaction_count' => (int) $item->transaction_count,
            ]);

        return [
            'escrow_balances' => $escrowBalances,
            'total_escrow_balance' => (float) $totalEscrowBalance,
            'fees_collected_30d' => (float) $feesCollected,
            'fee_trends' => $feeTrends,
        ];
    }

    /**
     * Get user activity report.
     */
    private function getUserActivity(): array
    {
        $last30Days = now()->subDays(30);

        // New registrations
        $newRegistrations = User::query()
            ->where('created_at', '>=', $last30Days)
            ->select(
                DB::raw("DATE_FORMAT(created_at, '%Y-%m-%d') as date"),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn ($item) => [
                'date' => $item->date,
                'count' => (int) $item->count,
            ]);

        // Email verifications
        $verifications = User::query()
            ->where('email_verified_at', '>=', $last30Days)
            ->select(
                DB::raw("DATE_FORMAT(email_verified_at, '%Y-%m-%d') as date"),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn ($item) => [
                'date' => $item->date,
                'count' => (int) $item->count,
            ]);

        // User statistics
        $userStats = User::query()
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN is_admin = 1 THEN 1 ELSE 0 END) as admins,
                SUM(CASE WHEN email_verified_at IS NOT NULL THEN 1 ELSE 0 END) as verified
            ')
            ->first();

        return [
            'new_registrations' => $newRegistrations,
            'verifications' => $verifications,
            'statistics' => [
                'total' => (int) ($userStats->total ?? 0),
                'admins' => (int) ($userStats->admins ?? 0),
                'verified' => (int) ($userStats->verified ?? 0),
            ],
        ];
    }
}
