<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\Recipient;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class RecipientController extends Controller
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
        $businessId = $request->get('business_id') ?? Auth::user()->current_business_id ?? session('current_business_id');
        
        $query = Recipient::query();

        if ($businessId) {
            $query->where('business_id', $businessId);
        } else {
            // Get all businesses user has access to
            $userBusinessIds = Auth::user()->businesses()->pluck('businesses.id');
            $query->whereIn('business_id', $userBusinessIds);
        }

        $recipients = $query->with('business')->latest()->paginate(15);

        return Inertia::render('recipients/index', [
            'recipients' => $recipients,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request): Response
    {
        $businessId = $request->get('business_id') ?? Auth::user()->current_business_id ?? session('current_business_id');
        $businesses = Auth::user()->businesses()->get();

        return Inertia::render('recipients/create', [
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
            'notes' => 'nullable|string',
        ]);

        // Check if business is active
        $business = Business::findOrFail($validated['business_id']);
        if (!$business->canPerformActions()) {
            return back()
                ->withErrors(['business_id' => "Cannot create recipient. Business is {$business->status}."])
                ->withInput();
        }

        // Wrap all database operations in a transaction
        $recipient = DB::transaction(function () use ($validated) {
            $recipient = Recipient::create($validated);
            $this->auditService->log('recipient.created', $recipient, $recipient->getAttributes());
            return $recipient;
        });

        return redirect()->route('recipients.index')
            ->with('success', 'Recipient created successfully.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Recipient $recipient): Response
    {
        $businesses = Auth::user()->businesses()->get();

        return Inertia::render('recipients/edit', [
            'recipient' => $recipient->load('business'),
            'businesses' => $businesses,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Recipient $recipient)
    {
        $validated = $request->validate([
            'business_id' => 'required|exists:businesses,id',
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'bank_account_details' => 'nullable|array',
            'payout_method' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        // Check if business is active
        $business = Business::findOrFail($validated['business_id']);
        if (!$business->canPerformActions()) {
            return back()
                ->withErrors(['business_id' => "Cannot update recipient. Business is {$business->status}."])
                ->withInput();
        }

        // Wrap all database operations in a transaction
        DB::transaction(function () use ($validated, $recipient) {
            $recipient->update($validated);
            $this->auditService->log('recipient.updated', $recipient, [
                'old' => $recipient->getOriginal(),
                'new' => $recipient->getChanges(),
            ]);
        });

        return redirect()->route('recipients.index')
            ->with('success', 'Recipient updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Recipient $recipient)
    {
        $this->auditService->log('recipient.deleted', $recipient, $recipient->getAttributes());

        $recipient->delete();

        return redirect()->route('recipients.index')
            ->with('success', 'Recipient deleted successfully.');
    }
}
