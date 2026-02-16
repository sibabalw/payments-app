<?php

namespace App\Http\Controllers;

use App\Models\Adjustment;
use App\Models\Business;
use App\Services\AuditService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class BenefitsController extends Controller
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * Display a listing of company benefits (recurring, company-wide adjustments)
     */
    public function index(Request $request): Response
    {
        $businessId = $request->get('business_id') ?? Auth::user()->current_business_id ?? session('current_business_id');

        $businesses = Auth::user()->businesses()->get();

        if (! $businessId && $businesses->isNotEmpty()) {
            $businessId = $businesses->first()->id;
        }

        // Get all company-wide recurring benefits (employee_id = null, period_start/end = null)
        $benefits = Adjustment::query();

        if ($businessId) {
            $benefits->where('business_id', $businessId);
        } else {
            $benefits->whereRaw('1 = 0'); // Return empty if no business
        }

        $benefits = $benefits->whereNull('employee_id')
            ->whereNull('period_start')
            ->whereNull('period_end')
            ->latest()
            ->paginate(15);

        return Inertia::render('benefits/index', [
            'benefits' => $benefits,
            'businesses' => $businesses,
            'selectedBusinessId' => $businessId,
        ]);
    }

    /**
     * Show the form for creating a new benefit.
     */
    public function create(Request $request): Response
    {
        $businessId = $request->get('business_id') ?? Auth::user()->current_business_id ?? session('current_business_id');
        $businesses = Auth::user()->businesses()->get();

        return Inertia::render('benefits/create', [
            'businesses' => $businesses,
            'selectedBusinessId' => $businessId,
        ]);
    }

    /**
     * Store a newly created benefit.
     * Automatically sets: company-wide (employee_id = null), recurring (period_start/end = null)
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'business_id' => 'required|exists:businesses,id',
            'name' => 'required|string|max:255',
            'type' => 'required|in:fixed,percentage',
            'amount' => 'required|numeric|min:0',
            'adjustment_type' => 'required|in:deduction,addition',
            'is_active' => 'boolean',
            'description' => 'nullable|string',
        ]);

        // Check if business is active
        $business = Business::findOrFail($validated['business_id']);
        if (! $business->canPerformActions()) {
            return back()
                ->withErrors(['business_id' => "Cannot create benefit. Business is {$business->status}."])
                ->withInput();
        }

        // Validate percentage amount
        if ($validated['type'] === 'percentage' && $validated['amount'] > 100) {
            return back()
                ->withErrors(['amount' => 'Percentage cannot exceed 100%.'])
                ->withInput();
        }

        // Auto-set: company-wide, recurring
        $validated['employee_id'] = null;
        $validated['period_start'] = null;
        $validated['period_end'] = null;

        // Set default is_active to true if not provided
        if (! isset($validated['is_active'])) {
            $validated['is_active'] = true;
        }

        $benefit = DB::transaction(function () use ($validated) {
            $benefit = Adjustment::create($validated);
            $this->auditService->log('benefit.created', $benefit, $benefit->getAttributes());

            return $benefit;
        });

        return redirect()->route('benefits.index', ['business_id' => $validated['business_id']])
            ->with('success', 'Benefit created successfully. It will apply to all employees every month.');
    }

    /**
     * Show the form for editing the specified benefit.
     */
    public function edit(Adjustment $benefit): Response
    {
        // Ensure this is a company-wide recurring benefit
        if ($benefit->employee_id !== null || $benefit->period_start !== null || $benefit->period_end !== null) {
            abort(404, 'This is not a company benefit.');
        }

        $businesses = Auth::user()->businesses()->get();

        return Inertia::render('benefits/edit', [
            'benefit' => $benefit->load('business'),
            'businesses' => $businesses,
        ]);
    }

    /**
     * Update the specified benefit.
     */
    public function update(Request $request, Adjustment $benefit)
    {
        // Ensure this is a company-wide recurring benefit
        if ($benefit->employee_id !== null || $benefit->period_start !== null || $benefit->period_end !== null) {
            abort(404, 'This is not a company benefit.');
        }

        $validated = $request->validate([
            'business_id' => 'required|exists:businesses,id',
            'name' => 'required|string|max:255',
            'type' => 'required|in:fixed,percentage',
            'amount' => 'required|numeric|min:0',
            'adjustment_type' => 'required|in:deduction,addition',
            'is_active' => 'boolean',
            'description' => 'nullable|string',
        ]);

        // Validate percentage amount
        if ($validated['type'] === 'percentage' && $validated['amount'] > 100) {
            return back()
                ->withErrors(['amount' => 'Percentage cannot exceed 100%.'])
                ->withInput();
        }

        // Ensure it remains company-wide and recurring
        $validated['employee_id'] = null;
        $validated['period_start'] = null;
        $validated['period_end'] = null;

        DB::transaction(function () use ($validated, $benefit) {
            $benefit->update($validated);
            $this->auditService->log('benefit.updated', $benefit, [
                'old' => $benefit->getOriginal(),
                'new' => $benefit->getChanges(),
            ]);
        });

        return redirect()->route('benefits.index', ['business_id' => $validated['business_id']])
            ->with('success', 'Benefit updated successfully.');
    }

    /**
     * Show the form to temporarily change a company benefit.
     */
    public function showTemporarilyChange(Adjustment $benefit): Response
    {
        // Ensure this is a company-wide recurring benefit
        if ($benefit->employee_id !== null || $benefit->period_start !== null || $benefit->period_end !== null) {
            abort(404, 'This is not a company benefit.');
        }

        return Inertia::render('benefits/temporarily-change', [
            'benefit' => [
                'id' => $benefit->id,
                'name' => $benefit->name,
                'type' => $benefit->type,
                'amount' => $benefit->amount,
                'adjustment_type' => $benefit->adjustment_type,
            ],
        ]);
    }

    /**
     * Temporarily change a company benefit for a specific period.
     * Creates a period-specific company-wide adjustment that overrides the benefit.
     */
    public function temporarilyChange(Request $request, Adjustment $benefit)
    {
        // Ensure this is a company-wide recurring benefit
        if ($benefit->employee_id !== null || $benefit->period_start !== null || $benefit->period_end !== null) {
            abort(404, 'This is not a company benefit.');
        }

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0',
            'period_start' => 'required|date',
            'period_end' => 'required|date',
            'description' => 'nullable|string',
        ]);

        // Ensure start <= end
        $periodStart = Carbon::parse($validated['period_start']);
        $periodEnd = Carbon::parse($validated['period_end']);

        if ($periodStart->gt($periodEnd)) {
            return back()
                ->withErrors(['period_start' => 'Start date must be before or equal to end date.'])
                ->withInput();
        }

        // Validate percentage if benefit is percentage type
        if ($benefit->type === 'percentage' && $validated['amount'] > 100) {
            return back()
                ->withErrors(['amount' => 'Percentage cannot exceed 100%.'])
                ->withInput();
        }

        // Create period-specific company-wide adjustment with same name
        $temporaryAdjustment = DB::transaction(function () use ($validated, $benefit) {
            $adjustment = Adjustment::create([
                'business_id' => $benefit->business_id,
                'employee_id' => null, // Company-wide
                'name' => $benefit->name,
                'type' => $benefit->type,
                'amount' => $validated['amount'],
                'adjustment_type' => $benefit->adjustment_type,
                'period_start' => $validated['period_start'],
                'period_end' => $validated['period_end'],
                'is_active' => true,
                'description' => $validated['description'] ?? "Temporary change to {$benefit->name}",
            ]);

            $this->auditService->log('benefit.temporarily_changed', $adjustment, [
                'original_benefit_id' => $benefit->id,
                'original_amount' => $benefit->amount,
                'new_amount' => $validated['amount'],
                'period' => [
                    'start' => $validated['period_start'],
                    'end' => $validated['period_end'],
                ],
            ]);

            return $adjustment;
        });

        return redirect()->route('benefits.index', ['business_id' => $benefit->business_id])
            ->with('success', "{$benefit->name} temporarily changed to {$validated['amount']} for the selected period. Will revert to {$benefit->amount} after.");
    }

    /**
     * Remove the specified benefit.
     */
    public function destroy(Adjustment $benefit)
    {
        // Ensure this is a company-wide recurring benefit
        if ($benefit->employee_id !== null || $benefit->period_start !== null || $benefit->period_end !== null) {
            abort(404, 'This is not a company benefit.');
        }

        $businessId = $benefit->business_id;

        $this->auditService->log('benefit.deleted', $benefit, $benefit->getAttributes());

        $benefit->delete();

        return redirect()->route('benefits.index', ['business_id' => $businessId])
            ->with('success', 'Benefit deleted successfully.');
    }
}
