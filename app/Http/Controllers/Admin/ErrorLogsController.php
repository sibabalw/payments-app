<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ErrorLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ErrorLogsController extends Controller
{
    /**
     * Display a listing of error logs with filtering.
     */
    public function index(Request $request): Response
    {
        $query = ErrorLog::query()
            ->with(['user:id,name,email']);

        // Filter by level
        if ($request->filled('level') && $request->level !== 'all') {
            $query->where('level', $request->level);
        }

        // Filter by type
        if ($request->filled('type') && $request->type !== 'all') {
            $query->where('type', $request->type);
        }

        // Filter by admin/user errors
        if ($request->filled('error_type') && $request->error_type !== 'all') {
            if ($request->error_type === 'admin') {
                $query->where('is_admin_error', true);
            } else {
                $query->where('is_admin_error', false);
            }
        }

        // Filter by notification status
        if ($request->filled('notified') && $request->notified !== 'all') {
            $query->where('notified', $request->notified === 'yes');
        }

        // Filter by date range
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // Search in message, exception, file, or URL
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('message', 'like', "%{$search}%")
                    ->orWhere('exception', 'like', "%{$search}%")
                    ->orWhere('file', 'like', "%{$search}%")
                    ->orWhere('url', 'like', "%{$search}%");
            });
        }

        // Order by created_at descending by default
        $sortField = $request->get('sort', 'created_at');
        $sortDirection = $request->get('direction', 'desc');
        $query->orderBy($sortField, $sortDirection);

        $logs = $query->paginate(50)->withQueryString();

        // Get unique levels and types for filter dropdowns
        $levels = ErrorLog::select('level')
            ->distinct()
            ->orderBy('level')
            ->pluck('level')
            ->toArray();

        $types = ErrorLog::select('type')
            ->distinct()
            ->orderBy('type')
            ->pluck('type')
            ->toArray();

        // Get error statistics
        $stats = [
            'total' => ErrorLog::count(),
            'critical' => ErrorLog::where('level', 'critical')->count(),
            'error' => ErrorLog::where('level', 'error')->count(),
            'warning' => ErrorLog::where('level', 'warning')->count(),
            'admin_errors' => ErrorLog::where('is_admin_error', true)->count(),
            'user_errors' => ErrorLog::where('is_admin_error', false)->count(),
            'notified' => ErrorLog::where('notified', true)->count(),
        ];

        return Inertia::render('admin/error-logs/index', [
            'logs' => $logs,
            'levels' => $levels,
            'types' => $types,
            'stats' => $stats,
            'filters' => [
                'level' => $request->level ?? 'all',
                'type' => $request->type ?? 'all',
                'error_type' => $request->error_type ?? 'all',
                'notified' => $request->notified ?? 'all',
                'date_from' => $request->date_from ?? '',
                'date_to' => $request->date_to ?? '',
                'search' => $request->search ?? '',
                'sort' => $sortField,
                'direction' => $sortDirection,
            ],
        ]);
    }

    /**
     * Show a single error log.
     */
    public function show(ErrorLog $errorLog): Response
    {
        $errorLog->loadMissing('user');

        return Inertia::render('admin/error-logs/show', [
            'errorLog' => $errorLog,
        ]);
    }
}
