<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class BusinessController extends Controller
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * Display a listing of all businesses with filtering.
     */
    public function index(Request $request): Response
    {
        $query = Business::query()
            ->with(['owner:id,name,email'])
            ->withCount(['employees', 'paymentSchedules', 'payrollSchedules']);

        // Filter by status
        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // Search by name or email
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('registration_number', 'like', "%{$search}%");
            });
        }

        // Order by
        $sortField = $request->get('sort', 'created_at');
        $sortDirection = $request->get('direction', 'desc');
        $query->orderBy($sortField, $sortDirection);

        $businesses = $query->paginate(20)->withQueryString();

        // Get status counts for filters
        $statusCounts = Business::query()
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        return Inertia::render('admin/businesses/index', [
            'businesses' => $businesses,
            'statusCounts' => [
                'all' => array_sum($statusCounts),
                'active' => $statusCounts['active'] ?? 0,
                'suspended' => $statusCounts['suspended'] ?? 0,
                'banned' => $statusCounts['banned'] ?? 0,
            ],
            'filters' => [
                'status' => $request->status ?? 'all',
                'search' => $request->search ?? '',
                'sort' => $sortField,
                'direction' => $sortDirection,
            ],
        ]);
    }

    /**
     * Update the status of a business.
     */
    public function updateStatus(Request $request, Business $business): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['active', 'suspended', 'banned'])],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $oldStatus = $business->status;
        $newStatus = $validated['status'];

        if ($oldStatus === $newStatus) {
            return back()->with('info', 'Business status is already set to '.$newStatus.'.');
        }

        $business->updateStatus($newStatus, $validated['reason'] ?? null);

        // Log the action
        $this->auditService->log(
            'business.status_changed',
            "Admin changed business status from {$oldStatus} to {$newStatus}",
            $business,
            [
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'reason' => $validated['reason'] ?? null,
            ]
        );

        $statusMessages = [
            'active' => 'Business has been activated successfully.',
            'suspended' => 'Business has been suspended.',
            'banned' => 'Business has been banned.',
        ];

        return back()->with('success', $statusMessages[$newStatus]);
    }
}
