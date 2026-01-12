<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\Receiver;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ReceiverController extends Controller
{
    public function __construct(
        protected AuditService $auditService
    ) {
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        $businessId = $request->get('business_id') ?? session('current_business_id');
        
        $query = Receiver::query();

        if ($businessId) {
            $query->where('business_id', $businessId);
        } else {
            // Get all businesses user has access to
            $userBusinessIds = Auth::user()->businesses()->pluck('businesses.id');
            $query->whereIn('business_id', $userBusinessIds);
        }

        $receivers = $query->with('business')->latest()->paginate(15);

        return Inertia::render('receivers/index', [
            'receivers' => $receivers,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request): Response
    {
        $businessId = $request->get('business_id') ?? session('current_business_id');
        $businesses = Auth::user()->businesses()->get();

        return Inertia::render('receivers/create', [
            'businesses' => $businesses,
            'selectedBusinessId' => $businessId,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'business_id' => 'required|exists:businesses,id',
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'bank_account_details' => 'nullable|array',
            'payout_method' => 'nullable|string|max:255',
        ]);

        // Check if business is active
        $business = Business::findOrFail($validated['business_id']);
        if (!$business->canPerformActions()) {
            return back()
                ->withErrors(['business_id' => "Cannot create receiver. Business is {$business->status}."])
                ->withInput();
        }

        $receiver = Receiver::create($validated);

        $this->auditService->log('receiver.created', $receiver, $receiver->getAttributes());

        return redirect()->route('receivers.index')
            ->with('success', 'Receiver created successfully.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Receiver $receiver): Response
    {
        $businesses = Auth::user()->businesses()->get();

        return Inertia::render('receivers/edit', [
            'receiver' => $receiver->load('business'),
            'businesses' => $businesses,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Receiver $receiver)
    {
        $validated = $request->validate([
            'business_id' => 'required|exists:businesses,id',
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'bank_account_details' => 'nullable|array',
            'payout_method' => 'nullable|string|max:255',
        ]);

        // Check if business is active
        $business = Business::findOrFail($validated['business_id']);
        if (!$business->canPerformActions()) {
            return back()
                ->withErrors(['business_id' => "Cannot update receiver. Business is {$business->status}."])
                ->withInput();
        }

        $receiver->update($validated);

        $this->auditService->log('receiver.updated', $receiver, [
            'old' => $receiver->getOriginal(),
            'new' => $receiver->getChanges(),
        ]);

        return redirect()->route('receivers.index')
            ->with('success', 'Receiver updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Receiver $receiver)
    {
        $this->auditService->log('receiver.deleted', $receiver, $receiver->getAttributes());

        $receiver->delete();

        return redirect()->route('receivers.index')
            ->with('success', 'Receiver deleted successfully.');
    }
}
