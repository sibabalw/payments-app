<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBusinessRequest;
use App\Mail\BusinessCreatedEmail;
use App\Models\Business;
use App\Models\User;
use App\Services\AuditService;
use App\Services\EmailService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class OnboardingController extends Controller
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * Display the onboarding page.
     */
    public function index(): Response|RedirectResponse
    {
        $user = Auth::user();

        // Admins skip onboarding - redirect to admin dashboard
        if ($user->is_admin) {
            return redirect()->route('admin.dashboard');
        }

        // If user has already completed onboarding, redirect to dashboard
        if ($user->onboarding_completed_at) {
            return redirect()->route('dashboard');
        }

        // If user already has businesses, mark onboarding as completed and redirect
        $hasBusinesses = $user->businesses()->count() > 0 || $user->ownedBusinesses()->count() > 0;

        if ($hasBusinesses) {
            $user->update(['onboarding_completed_at' => now()]);

            return redirect()->route('dashboard');
        }

        return Inertia::render('onboarding/index');
    }

    /**
     * Store a newly created business during onboarding.
     */
    public function store(StoreBusinessRequest $request)
    {
        $validated = $request->validated();

        // Handle logo upload first (outside transaction)
        $logoPath = null;
        if ($request->hasFile('logo')) {
            $logo = $request->file('logo');
            $logoPath = $logo->store('businesses/logos', 'public');
        }

        // Remove logo from validated array since we handle it separately
        unset($validated['logo']);

        // Default business_type when not provided (column is NOT NULL)
        $validated['business_type'] = $validated['business_type'] ?? 'small_business';

        try {
            $userId = Auth::id();
            $businessId = null;

            // Wrap all database operations in a transaction
            DB::transaction(function () use ($logoPath, $validated, &$businessId) {
                $user = Auth::user();

                // Create business
                $business = Business::create([
                    'user_id' => $user->id,
                    'logo' => $logoPath,
                    ...$validated,
                ]);

                $businessId = $business->id;

                // Add user as owner in pivot table
                $business->users()->attach($user->id, ['role' => 'owner']);

                // Set as current business and mark onboarding as completed
                $user->update([
                    'current_business_id' => $business->id,
                    'onboarding_completed_at' => now(),
                ]);

                // Log audit trail
                $this->auditService->log('business.created', $business, $business->getAttributes());
            });

            // Queue business created email after transaction commits
            // Business is already committed, so queue directly
            $user = User::findOrFail($userId);
            $business = Business::findOrFail($businessId);
            $emailService = app(EmailService::class);
            $emailService->send($user, new BusinessCreatedEmail($user, $business), 'business_created');

            return redirect()->route('dashboard')
                ->with('success', 'Business created successfully. Welcome to SwiftPay!');

        } catch (\Exception $e) {
            // If transaction failed, clean up uploaded file
            if ($logoPath && Storage::disk('public')->exists($logoPath)) {
                Storage::disk('public')->delete($logoPath);
            }

            // Re-throw the exception to show error to user
            throw $e;
        }
    }

    /**
     * Skip onboarding and redirect to dashboard.
     */
    public function skip()
    {
        // Mark onboarding as completed
        Auth::user()->update(['onboarding_completed_at' => now()]);

        return redirect()->route('dashboard')
            ->with('success', 'Welcome to SwiftPay! You can add a business later from the dashboard.');
    }
}
