<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAdminRequest;
use App\Mail\AdminAddedEmail;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

class UsersController extends Controller
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * Show the form for creating a new admin user.
     */
    public function create(): Response
    {
        return Inertia::render('admin/users/create');
    }

    /**
     * Store a newly created admin user and send notification email.
     */
    public function store(StoreAdminRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $user = User::query()->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'is_admin' => true,
        ]);

        Mail::to($user->email)->queue(new AdminAddedEmail($user, $request->user()));

        $this->auditService->log(
            'user.admin_created',
            'Admin created new administrator',
            $user,
            ['created_by' => $request->user()->id]
        );

        return to_route('admin.users.index')
            ->with('success', 'Administrator added successfully. They have been sent a notification email.');
    }

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

        if (! $wasAdmin) {
            Mail::to($user->email)->queue(new AdminAddedEmail($user, $request->user()));
        }

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
            : 'Admin privileges granted to user. They have been sent a notification email.';

        return back()->with('success', $message);
    }
}
