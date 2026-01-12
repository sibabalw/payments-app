<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    /**
     * Display a listing of audit logs.
     */
    public function index(Request $request): Response
    {
        $businessId = $request->get('business_id');
        $userId = $request->get('user_id');
        $action = $request->get('action');
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');

        $query = AuditLog::query()->with(['user', 'business']);

        // Filter by business
        if ($businessId) {
            $query->where('business_id', $businessId);
        } else {
            // Only show logs for businesses user has access to
            $userBusinessIds = Auth::user()->businesses()->pluck('businesses.id');
            $query->whereIn('business_id', $userBusinessIds);
        }

        if ($userId) {
            $query->where('user_id', $userId);
        }

        if ($action) {
            $query->where('action', 'like', "%{$action}%");
        }

        if ($dateFrom) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        $logs = $query->latest()->paginate(50);

        return Inertia::render('audit-logs/index', [
            'logs' => $logs,
            'filters' => [
                'business_id' => $businessId,
                'user_id' => $userId,
                'action' => $action,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
        ]);
    }

    /**
     * Display the specified audit log.
     */
    public function show(AuditLog $auditLog): Response
    {
        $auditLog->load(['user', 'business']);

        return Inertia::render('audit-logs/show', [
            'log' => $auditLog,
        ]);
    }
}
