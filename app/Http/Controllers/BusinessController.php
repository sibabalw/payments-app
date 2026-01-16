<?php

namespace App\Http\Controllers;

use App\Mail\BusinessCreatedEmail;
use App\Mail\BusinessStatusChangedEmail;
use App\Models\Business;
use App\Services\AuditService;
use App\Services\EmailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class BusinessController extends Controller
{
    public function __construct(
        protected AuditService $auditService
    ) {
    }

    /**
     * Display a listing of the resource.
     */
    public function index(): Response
    {
        $user = Auth::user();
        
        // Get all businesses user has access to (owned + associated)
        $owned = $user->ownedBusinesses()->with('owner')->get();
        $associated = $user->businesses()->with('owner')->get();
        $businesses = $owned->merge($associated)->unique('id')->values();

        return Inertia::render('businesses/index', [
            'businesses' => $businesses,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        return Inertia::render('businesses/create');
    }

    /**
     * Store a newly created resource in storage.
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

                // If this is user's first business or they have no current business, set it as current
                if (!$user->current_business_id) {
                    $user->update(['current_business_id' => $business->id]);
                }

                // Log audit trail
                $this->auditService->log('business.created', $business, $business->getAttributes());
            });

            // If we get here, transaction succeeded
            // Send business created email (non-critical, happens after transaction)
            $emailService = app(EmailService::class);
            $emailService->send($user, new BusinessCreatedEmail($user, $business), 'business_created');

            return redirect()->route('businesses.index')
                ->with('success', 'Business created successfully.');
                
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
     * Update the specified resource in storage.
     */
    public function update(Request $request, Business $business)
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

        // Store old logo path for cleanup
        $oldLogoPath = $business->logo;
        $newLogoPath = null;
        
        // Handle new logo upload first (outside transaction)
        if ($request->hasFile('logo')) {
            $logo = $request->file('logo');
            $newLogoPath = $logo->store('businesses/logos', 'public');
            $validated['logo'] = $newLogoPath;
        }

        try {
            // Wrap all database operations in a transaction
            DB::transaction(function () use ($validated, $business) {
                $business->update($validated);

                $this->auditService->log('business.updated', $business, [
                    'old' => $business->getOriginal(),
                    'new' => $business->getChanges(),
                ]);
            });

            // If transaction succeeded, delete old logo (if new one was uploaded)
            if ($newLogoPath && $oldLogoPath && Storage::disk('public')->exists($oldLogoPath)) {
                Storage::disk('public')->delete($oldLogoPath);
            }

            return redirect()->route('businesses.index')
                ->with('success', 'Business updated successfully.');
                
        } catch (\Exception $e) {
            // If transaction failed, clean up newly uploaded file (keep old one)
            if ($newLogoPath && Storage::disk('public')->exists($newLogoPath)) {
                Storage::disk('public')->delete($newLogoPath);
            }
            
            // Re-throw the exception to show error to user
            throw $e;
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Business $business)
    {
        $this->auditService->log('business.deleted', $business, $business->getAttributes());

        $business->delete();

        return redirect()->route('businesses.index')
            ->with('success', 'Business deleted successfully.');
    }

    /**
     * Switch to a different business (store in database).
     */
    public function switch(Business $business)
    {
        $user = Auth::user();

        // Verify user has access to this business
        $hasAccess = $user->ownedBusinesses()->where('businesses.id', $business->id)->exists()
            || $user->businesses()->where('businesses.id', $business->id)->exists();

        if (!$hasAccess) {
            return redirect()->back()
                ->with('error', 'You do not have access to this business.');
        }

        // Prevent switching to banned or suspended businesses
        if (!$business->canPerformActions()) {
            return redirect()->back()
                ->with('error', "Cannot switch to this business. Status: {$business->status}.");
        }

        // Save to database
        $user->update(['current_business_id' => $business->id]);

        // Also update session for backward compatibility
        session(['current_business_id' => $business->id]);

        return redirect()->back()
            ->with('success', "Switched to {$business->name}.");
    }

    /**
     * Update business status (admin only)
     */
    public function updateStatus(Request $request, Business $business)
    {
        $validated = $request->validate([
            'status' => 'required|in:active,suspended,banned',
            'status_reason' => 'nullable|string|max:1000',
        ]);

        $oldStatus = $business->status;
        $business->updateStatus($validated['status'], $validated['status_reason'] ?? null);

        $this->auditService->log('business.status_updated', $business, [
            'old_status' => $oldStatus,
            'new_status' => $validated['status'],
            'reason' => $validated['status_reason'] ?? null,
        ]);

        // Send business status changed email to owner
        $user = $business->owner;
        $emailService = app(EmailService::class);
        $emailService->send(
            $user,
            new BusinessStatusChangedEmail(
                $user,
                $business,
                $oldStatus,
                $validated['status'],
                $validated['status_reason'] ?? null
            ),
            'business_status_changed'
        );

        return redirect()->back()
            ->with('success', "Business status updated to {$validated['status']}.");
    }
}
