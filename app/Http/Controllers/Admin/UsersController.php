<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UsersController extends Controller
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * Display a listing of all users with filtering.
     */
    public function index(Request $request): Response
    {
        $query = User::query()
            ->withCount(['businesses', 'ownedBusinesses']);

        // Filter by admin status
        if ($request->filled('role') && $request->role !== 'all') {
            if ($request->role === 'admin') {
                $query->where('is_admin', true);
            } else {
                $query->where('is_admin', false);
            }
        }

        // Filter by verification status
        if ($request->filled('verified') && $request->verified !== 'all') {
            if ($request->verified === 'verified') {
                $query->whereNotNull('email_verified_at');
            } else {
                $query->whereNull('email_verified_at');
            }
        }

        // Search by name or email
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Order by
        $sortField = $request->get('sort', 'created_at');
        $sortDirection = $request->get('direction', 'desc');
        $query->orderBy($sortField, $sortDirection);

        $users = $query->paginate(20)->withQueryString();

        // Get role counts for filters
        $roleCounts = [
            'all' => User::count(),
            'admin' => User::where('is_admin', true)->count(),
            'user' => User::where('is_admin', false)->count(),
        ];

        return Inertia::render('admin/users/index', [
            'users' => $users,
            'roleCounts' => $roleCounts,
            'filters' => [
                'role' => $request->role ?? 'all',
                'verified' => $request->verified ?? 'all',
                'search' => $request->search ?? '',
                'sort' => $sortField,
                'direction' => $sortDirection,
            ],
        ]);
    }

    /**
     * Toggle admin status for a user.
     */
    public function toggleAdmin(Request $request, User $user): RedirectResponse
    {
        // Prevent removing own admin status
        if ($user->id === $request->user()->id) {
            return back()->withErrors(['error' => 'You cannot change your own admin status.']);
        }

        $wasAdmin = $user->is_admin;
        $user->update(['is_admin' => ! $wasAdmin]);

        $action = $wasAdmin ? 'removed admin from' : 'granted admin to';

        $this->auditService->log(
            'user.admin_status_changed',
            "Admin {$action} user",
            $user,
            [
                'was_admin' => $wasAdmin,
                'is_admin' => ! $wasAdmin,
            ]
        );

        $message = $wasAdmin
            ? 'Admin privileges removed from user.'
            : 'Admin privileges granted to user.';

        return back()->with('success', $message);
    }
}
