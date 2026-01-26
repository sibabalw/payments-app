<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogsController extends Controller
{
    /**
     * Display a listing of audit logs with filtering.
     */
    public function index(Request $request): Response
    {
        $query = AuditLog::query()
            ->with(['user:id,name,email', 'business:id,name']);

        // Filter by action type
        if ($request->filled('action') && $request->action !== 'all') {
            $query->where('action', 'like', "%{$request->action}%");
        }

        // Filter by user
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // Filter by business
        if ($request->filled('business_id')) {
            $query->where('business_id', $request->business_id);
        }

        // Filter by date range
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // Search in action or changes
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('action', 'like', "%{$search}%")
                    ->orWhere('model_type', 'like', "%{$search}%")
                    ->orWhere('ip_address', 'like', "%{$search}%");
            });
        }

        // Order by created_at descending by default
        $sortField = $request->get('sort', 'created_at');
        $sortDirection = $request->get('direction', 'desc');
        $query->orderBy($sortField, $sortDirection);

        $logs = $query->paginate(50)->withQueryString();

        // Get unique actions for filter dropdown
        $actionTypes = AuditLog::select('action')
            ->distinct()
            ->orderBy('action')
            ->pluck('action')
            ->toArray();

        return Inertia::render('admin/audit-logs/index', [
            'logs' => $logs,
            'actionTypes' => $actionTypes,
            'filters' => [
                'action' => $request->action ?? 'all',
                'user_id' => $request->user_id ?? '',
                'business_id' => $request->business_id ?? '',
                'date_from' => $request->date_from ?? '',
                'date_to' => $request->date_to ?? '',
                'search' => $request->search ?? '',
                'sort' => $sortField,
                'direction' => $sortDirection,
            ],
        ]);
    }
}
