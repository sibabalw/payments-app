<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\EscrowDeposit;
use App\Models\PaymentJob;
use App\Models\PayrollJob;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Get database-agnostic date format expression.
     */
    private function dateFormat(string $column, string $format = '%Y-%m-%d'): string
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            // PostgreSQL: to_char(column, 'YYYY-MM-DD')
            $pgFormat = match ($format) {
                '%Y-%m-%d' => 'YYYY-MM-DD',
                '%Y-%m' => 'YYYY-MM',
                default => 'YYYY-MM-DD',
            };

            return "to_char({$column}, '{$pgFormat}')";
        } else {
            // MySQL/MariaDB: DATE_FORMAT(column, '%Y-%m-%d')
            return "DATE_FORMAT({$column}, '{$format}')";
        }
    }

    /**
     * Display the admin dashboard with platform-wide metrics.
     */
    public function index(): Response
    {
        // Business metrics by status
        $businessMetrics = Business::query()
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $totalBusinesses = array_sum($businessMetrics);

        // User metrics - single query with conditional aggregation
        $userMetricsData = User::query()
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN is_admin THEN 1 ELSE 0 END) as admins,
                SUM(CASE WHEN email_verified_at IS NOT NULL THEN 1 ELSE 0 END) as verified
            ')
            ->first();

        $userMetrics = [
            'total' => (int) ($userMetricsData->total ?? 0),
            'admins' => (int) ($userMetricsData->admins ?? 0),
            'verified' => (int) ($userMetricsData->verified ?? 0),
        ];

        // Payment/Payroll job metrics
        $paymentJobMetrics = PaymentJob::query()
            ->select('status', DB::raw('COUNT(*) as count'), DB::raw('COALESCE(SUM(amount), 0) as total_amount'))
            ->groupBy('status')
            ->get()
            ->keyBy('status')
            ->toArray();

        $payrollJobMetrics = PayrollJob::query()
            ->select('status', DB::raw('COUNT(*) as count'), DB::raw('COALESCE(SUM(gross_salary), 0) as total_amount'))
            ->groupBy('status')
            ->get()
            ->keyBy('status')
            ->toArray();

        // Total escrow balance across platform
        $totalEscrowBalance = EscrowDeposit::query()
            ->where('status', 'confirmed')
            ->sum('authorized_amount');

        // Recent businesses
        $recentBusinesses = Business::query()
            ->with('owner:id,name,email')
            ->select('id', 'name', 'status', 'status_reason', 'status_changed_at', 'user_id', 'created_at')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        // Recent status changes
        $recentStatusChanges = Business::query()
            ->with('owner:id,name,email')
            ->whereNotNull('status_changed_at')
            ->select('id', 'name', 'status', 'status_reason', 'status_changed_at', 'user_id')
            ->orderByDesc('status_changed_at')
            ->limit(10)
            ->get();

        // Monthly trends (last 6 months)
        $monthExpr = $this->dateFormat('processed_at', '%Y-%m');
        $monthlyPayments = PaymentJob::query()
            ->where('status', 'succeeded')
            ->where('processed_at', '>=', now()->subMonths(6))
            ->select(
                DB::raw("{$monthExpr} as month"),
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(amount) as total')
            )
            ->groupBy(DB::raw($monthExpr))
            ->orderBy('month')
            ->get();

        $monthlyPayroll = PayrollJob::query()
            ->where('status', 'succeeded')
            ->where('processed_at', '>=', now()->subMonths(6))
            ->select(
                DB::raw("{$monthExpr} as month"),
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(gross_salary) as total')
            )
            ->groupBy(DB::raw($monthExpr))
            ->orderBy('month')
            ->get();

        return Inertia::render('admin/dashboard/index', [
            'businessMetrics' => [
                'total' => $totalBusinesses,
                'active' => $businessMetrics['active'] ?? 0,
                'suspended' => $businessMetrics['suspended'] ?? 0,
                'banned' => $businessMetrics['banned'] ?? 0,
            ],
            'userMetrics' => $userMetrics,
            'paymentJobMetrics' => [
                'succeeded' => $paymentJobMetrics['succeeded'] ?? ['count' => 0, 'total_amount' => 0],
                'failed' => $paymentJobMetrics['failed'] ?? ['count' => 0, 'total_amount' => 0],
                'pending' => $paymentJobMetrics['pending'] ?? ['count' => 0, 'total_amount' => 0],
                'processing' => $paymentJobMetrics['processing'] ?? ['count' => 0, 'total_amount' => 0],
            ],
            'payrollJobMetrics' => [
                'succeeded' => $payrollJobMetrics['succeeded'] ?? ['count' => 0, 'total_amount' => 0],
                'failed' => $payrollJobMetrics['failed'] ?? ['count' => 0, 'total_amount' => 0],
                'pending' => $payrollJobMetrics['pending'] ?? ['count' => 0, 'total_amount' => 0],
                'processing' => $payrollJobMetrics['processing'] ?? ['count' => 0, 'total_amount' => 0],
            ],
            'totalEscrowBalance' => $totalEscrowBalance,
            'recentBusinesses' => $recentBusinesses,
            'recentStatusChanges' => $recentStatusChanges,
            'monthlyPayments' => $monthlyPayments,
            'monthlyPayroll' => $monthlyPayroll,
        ]);
    }
}
