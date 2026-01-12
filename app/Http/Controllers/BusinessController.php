<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
        $businesses = $user->businesses()->with('owner')->get();

        return Inertia::render('businesses/index', [
            'businesses' => $businesses,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'business_type' => 'nullable|in:small_business,medium_business,large_business,sole_proprietorship,partnership,corporation,other',
            'registration_number' => 'nullable|string|max:255',
            'tax_id' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:255',
            'website' => 'nullable|url|max:255',
            'street_address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'contact_person_name' => 'nullable|string|max:255',
        ]);

        $business = Business::create([
            'user_id' => Auth::id(),
            ...$validated,
        ]);

        // Add user as owner in pivot table
        $business->users()->attach(Auth::id(), ['role' => 'owner']);

        $this->auditService->log('business.created', $business, $business->getAttributes());

        return redirect()->route('businesses.index')
            ->with('success', 'Business created successfully.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Business $business)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'business_type' => 'nullable|in:small_business,medium_business,large_business,sole_proprietorship,partnership,corporation,other',
            'registration_number' => 'nullable|string|max:255',
            'tax_id' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:255',
            'website' => 'nullable|url|max:255',
            'street_address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'contact_person_name' => 'nullable|string|max:255',
        ]);

        $business->update($validated);

        $this->auditService->log('business.updated', $business, [
            'old' => $business->getOriginal(),
            'new' => $business->getChanges(),
        ]);

        return redirect()->route('businesses.index')
            ->with('success', 'Business updated successfully.');
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
     * Switch to a different business (store in session).
     */
    public function switch(Business $business)
    {
        // Prevent switching to banned or suspended businesses
        if (!$business->canPerformActions()) {
            return redirect()->back()
                ->with('error', "Cannot switch to this business. Status: {$business->status}.");
        }

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

        return redirect()->back()
            ->with('success', "Business status updated to {$validated['status']}.");
    }
}
