<?php

namespace App\Http\Controllers;

use App\Mail\BusinessCreatedEmail;
use App\Models\Business;
use App\Services\AuditService;
use App\Services\EmailService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class OnboardingController extends Controller
{
    public function __construct(
        protected AuditService $auditService
    ) {
    }

    /**
     * Display the onboarding page.
     */
    public function index(): Response|RedirectResponse
    {
        $user = Auth::user();

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
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'business_type' => 'nullable|in:small_business,medium_business,large_business,sole_proprietorship,partnership,corporation,other',
            'registration_number' => 'nullable|string|max:255',
            'tax_id' => 'nullable|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:255',
            'website' => 'nullable|url|max:255',
            'street_address' => 'nullable|string|max:255',
            'city' => 'required|string|max:255',
            'province' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:255',
            'country' => 'required|string|max:255',
            'description' => 'nullable|string',
            'contact_person_name' => 'required|string|max:255',
        ]);

        // Handle logo upload first (outside transaction)
        $logoPath = null;
        if ($request->hasFile('logo')) {
            $logo = $request->file('logo');
            $logoPath = $logo->store('businesses/logos', 'public');
        }

        // Remove logo from validated array since we handle it separately
        unset($validated['logo']);
        
        try {
            // Wrap all database operations in a transaction
            DB::transaction(function () use ($logoPath, $validated, &$business, &$user) {
                $user = Auth::user();
                
                // Create business
                $business = Business::create([
                    'user_id' => $user->id,
                    'logo' => $logoPath,
                    ...$validated,
                ]);

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

            // If we get here, transaction succeeded
            // Send business created email (non-critical, happens after transaction)
            $emailService = app(EmailService::class);
            $emailService->send($user, new BusinessCreatedEmail($user, $business), 'business_created');

            return redirect()->route('dashboard')
                ->with('success', 'Business created successfully. Welcome to Swift Pay!');
                
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
            ->with('success', 'Welcome to Swift Pay! You can add a business later from the dashboard.');
    }
}
