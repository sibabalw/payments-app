<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\Employee;
use App\Models\LeaveEntry;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class LeaveController extends Controller
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * Display a listing of leave entries
     */
    public function index(Request $request): Response
    {
        $businessId = $request->get('business_id') ?? Auth::user()->current_business_id ?? session('current_business_id');

        if (! $businessId) {
            $businessId = Auth::user()->businesses()->first()?->id;
        }

        $query = LeaveEntry::where('business_id', $businessId)
            ->with(['employee', 'createdBy']);

        // Filters
        if ($request->has('employee_id') && $request->employee_id) {
            $query->where('employee_id', $request->employee_id);
        }

        if ($request->has('leave_type') && $request->leave_type) {
            $query->where('leave_type', $request->leave_type);
        }

        if ($request->has('start_date') && $request->start_date) {
            $query->where('end_date', '>=', $request->start_date);
        }

        if ($request->has('end_date') && $request->end_date) {
            $query->where('start_date', '<=', $request->end_date);
        }

        $leaveEntries = $query->latest('start_date')->paginate(15);
        $employees = Employee::where('business_id', $businessId)->get();
        $businesses = Auth::user()->businesses()->get();

        return Inertia::render('leave/index', [
            'leaveEntries' => $leaveEntries,
            'employees' => $employees,
            'businesses' => $businesses,
            'selectedBusinessId' => $businessId,
            'filters' => $request->only(['employee_id', 'leave_type', 'start_date', 'end_date']),
        ]);
    }

    /**
     * Show the form for creating a new leave entry
     */
    public function create(Request $request): Response
    {
        $businessId = $request->get('business_id') ?? Auth::user()->current_business_id ?? session('current_business_id');
        $employeeId = $request->get('employee_id');

        $businesses = Auth::user()->businesses()->get();
        $employees = $businessId
            ? Employee::where('business_id', $businessId)->get()
            : collect();

        return Inertia::render('leave/create', [
            'businesses' => $businesses,
            'employees' => $employees,
            'selectedBusinessId' => $businessId,
            'selectedEmployeeId' => $employeeId,
        ]);
    }

    /**
     * Store a newly created leave entry
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'business_id' => 'required|exists:businesses,id',
            'employee_id' => 'required|exists:employees,id',
            'leave_type' => 'required|in:paid,unpaid,sick,public_holiday,other',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'hours' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        // Ensure employee belongs to business
        $employee = Employee::findOrFail($validated['employee_id']);
        if ($employee->business_id !== $validated['business_id']) {
            return back()
                ->withErrors(['employee_id' => 'Employee does not belong to the selected business.'])
                ->withInput();
        }

        // For paid leave, calculate hours if not provided
        if ($validated['leave_type'] === 'paid' && ! isset($validated['hours'])) {
            // Default to 8 hours per day for paid leave
            $start = \Carbon\Carbon::parse($validated['start_date']);
            $end = \Carbon\Carbon::parse($validated['end_date']);
            $days = $start->diffInDays($end) + 1;
            $validated['hours'] = $days * 8;
        }

        $leaveEntry = DB::transaction(function () use ($validated) {
            $leaveEntry = LeaveEntry::create([
                'employee_id' => $validated['employee_id'],
                'business_id' => $validated['business_id'],
                'leave_type' => $validated['leave_type'],
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
                'hours' => $validated['hours'] ?? 0,
                'notes' => $validated['notes'] ?? null,
                'created_by' => Auth::id(),
            ]);

            $this->auditService->log('leave_entry.created', $leaveEntry, $leaveEntry->getAttributes());

            return $leaveEntry;
        });

        return redirect()->route('leave.index', ['business_id' => $validated['business_id']])
            ->with('success', 'Leave entry created successfully.');
    }

    /**
     * Show the form for editing the specified leave entry
     */
    public function edit(LeaveEntry $leaveEntry): Response
    {
        $businesses = Auth::user()->businesses()->get();
        $employees = Employee::where('business_id', $leaveEntry->business_id)->get();

        return Inertia::render('leave/edit', [
            'leaveEntry' => $leaveEntry->load(['employee', 'business']),
            'businesses' => $businesses,
            'employees' => $employees,
        ]);
    }

    /**
     * Update the specified leave entry
     */
    public function update(Request $request, LeaveEntry $leaveEntry)
    {
        $validated = $request->validate([
            'business_id' => 'required|exists:businesses,id',
            'employee_id' => 'required|exists:employees,id',
            'leave_type' => 'required|in:paid,unpaid,sick,public_holiday,other',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'hours' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        // Ensure employee belongs to business
        $employee = Employee::findOrFail($validated['employee_id']);
        if ($employee->business_id !== $validated['business_id']) {
            return back()
                ->withErrors(['employee_id' => 'Employee does not belong to the selected business.'])
                ->withInput();
        }

        // For paid leave, calculate hours if not provided
        if ($validated['leave_type'] === 'paid' && ! isset($validated['hours'])) {
            $start = \Carbon\Carbon::parse($validated['start_date']);
            $end = \Carbon\Carbon::parse($validated['end_date']);
            $days = $start->diffInDays($end) + 1;
            $validated['hours'] = $days * 8;
        }

        DB::transaction(function () use ($validated, $leaveEntry) {
            $leaveEntry->update([
                'employee_id' => $validated['employee_id'],
                'business_id' => $validated['business_id'],
                'leave_type' => $validated['leave_type'],
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
                'hours' => $validated['hours'] ?? 0,
                'notes' => $validated['notes'] ?? null,
            ]);

            $this->auditService->log('leave_entry.updated', $leaveEntry, [
                'old' => $leaveEntry->getOriginal(),
                'new' => $leaveEntry->getChanges(),
            ]);
        });

        return redirect()->route('leave.index', ['business_id' => $validated['business_id']])
            ->with('success', 'Leave entry updated successfully.');
    }

    /**
     * Remove the specified leave entry
     */
    public function destroy(LeaveEntry $leaveEntry)
    {
        $businessId = $leaveEntry->business_id;

        $this->auditService->log('leave_entry.deleted', $leaveEntry, $leaveEntry->getAttributes());

        $leaveEntry->delete();

        return redirect()->route('leave.index', ['business_id' => $businessId])
            ->with('success', 'Leave entry deleted successfully.');
    }
}
