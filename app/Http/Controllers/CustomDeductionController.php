<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\CustomDeduction;
use App\Models\Employee;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CustomDeductionController extends Controller
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * Display a listing of custom deductions for a business (company-wide)
     */
    public function index(Request $request): Response
    {
        $businessId = $request->get('business_id') ?? Auth::user()->current_business_id ?? session('current_business_id');

        if (! $businessId) {
            $businessId = Auth::user()->businesses()->first()?->id;
        }

        $query = CustomDeduction::where('business_id', $businessId)
            ->whereNull('employee_id'); // Only company-wide deductions

        $deductions = $query->latest()->paginate(15);
        $businesses = Auth::user()->businesses()->get();

        return Inertia::render('deductions/index', [
            'deductions' => $deductions,
            'businesses' => $businesses,
            'selectedBusinessId' => $businessId,
        ]);
    }

    /**
     * Display deductions for a specific employee
     */
    public function employeeIndex(Employee $employee): Response
    {
        $deductions = CustomDeduction::where('business_id', $employee->business_id)
            ->where(function ($query) use ($employee) {
                $query->whereNull('employee_id') // Company-wide
                    ->orWhere('employee_id', $employee->id); // Employee-specific
            })
            ->where('is_active', true)
            ->latest()
            ->get();

        return Inertia::render('deductions/employee-index', [
            'employee' => $employee->load('business'),
            'deductions' => $deductions,
        ]);
    }

    /**
     * Show the form for creating a new deduction.
     */
    public function create(Request $request): Response
    {
        $businessId = $request->get('business_id') ?? Auth::user()->current_business_id ?? session('current_business_id');
        $employeeId = $request->get('employee_id');

        $businesses = Auth::user()->businesses()->get();
        $employee = $employeeId ? Employee::findOrFail($employeeId) : null;

        return Inertia::render('deductions/create', [
            'businesses' => $businesses,
            'selectedBusinessId' => $businessId,
            'employee' => $employee,
        ]);
    }

    /**
     * Store a newly created deduction.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'business_id' => 'required|exists:businesses,id',
            'employee_id' => 'nullable|exists:employees,id',
            'name' => 'required|string|max:255',
            'type' => 'required|in:fixed,percentage',
            'amount' => 'required|numeric|min:0',
            'is_active' => 'boolean',
            'description' => 'nullable|string',
        ]);

        // If employee_id is provided, ensure it belongs to the business
        if ($validated['employee_id']) {
            $employee = Employee::findOrFail($validated['employee_id']);
            if ($employee->business_id !== $validated['business_id']) {
                return back()
                    ->withErrors(['employee_id' => 'Employee does not belong to the selected business.'])
                    ->withInput();
            }
        }

        // Check if business is active
        $business = Business::findOrFail($validated['business_id']);
        if (! $business->canPerformActions()) {
            return back()
                ->withErrors(['business_id' => "Cannot create deduction. Business is {$business->status}."])
                ->withInput();
        }

        // Validate percentage amount
        if ($validated['type'] === 'percentage' && $validated['amount'] > 100) {
            return back()
                ->withErrors(['amount' => 'Percentage cannot exceed 100%.'])
                ->withInput();
        }

        $deduction = DB::transaction(function () use ($validated) {
            $deduction = CustomDeduction::create($validated);
            $this->auditService->log('custom_deduction.created', $deduction, $deduction->getAttributes());

            return $deduction;
        });

        if ($validated['employee_id']) {
            return redirect()->route('employees.edit', $validated['employee_id'])
                ->with('success', 'Custom deduction created successfully.');
        }

        return redirect()->route('deductions.index', ['business_id' => $validated['business_id']])
            ->with('success', 'Custom deduction created successfully.');
    }

    /**
     * Show the form for editing the specified deduction.
     */
    public function edit(CustomDeduction $deduction): Response
    {
        $businesses = Auth::user()->businesses()->get();
        $employee = $deduction->employee;

        return Inertia::render('deductions/edit', [
            'deduction' => $deduction->load(['business', 'employee']),
            'businesses' => $businesses,
            'employee' => $employee,
        ]);
    }

    /**
     * Update the specified deduction.
     */
    public function update(Request $request, CustomDeduction $deduction)
    {
        $validated = $request->validate([
            'business_id' => 'required|exists:businesses,id',
            'employee_id' => 'nullable|exists:employees,id',
            'name' => 'required|string|max:255',
            'type' => 'required|in:fixed,percentage',
            'amount' => 'required|numeric|min:0',
            'is_active' => 'boolean',
            'description' => 'nullable|string',
        ]);

        // If employee_id is provided, ensure it belongs to the business
        if ($validated['employee_id']) {
            $employee = Employee::findOrFail($validated['employee_id']);
            if ($employee->business_id !== $validated['business_id']) {
                return back()
                    ->withErrors(['employee_id' => 'Employee does not belong to the selected business.'])
                    ->withInput();
            }
        }

        // Validate percentage amount
        if ($validated['type'] === 'percentage' && $validated['amount'] > 100) {
            return back()
                ->withErrors(['amount' => 'Percentage cannot exceed 100%.'])
                ->withInput();
        }

        DB::transaction(function () use ($validated, $deduction) {
            $deduction->update($validated);
            $this->auditService->log('custom_deduction.updated', $deduction, [
                'old' => $deduction->getOriginal(),
                'new' => $deduction->getChanges(),
            ]);
        });

        if ($validated['employee_id']) {
            return redirect()->route('employees.edit', $validated['employee_id'])
                ->with('success', 'Custom deduction updated successfully.');
        }

        return redirect()->route('deductions.index', ['business_id' => $validated['business_id']])
            ->with('success', 'Custom deduction updated successfully.');
    }

    /**
     * Remove the specified deduction.
     */
    public function destroy(CustomDeduction $deduction)
    {
        $businessId = $deduction->business_id;
        $employeeId = $deduction->employee_id;

        $this->auditService->log('custom_deduction.deleted', $deduction, $deduction->getAttributes());

        $deduction->delete();

        if ($employeeId) {
            return redirect()->route('employees.edit', $employeeId)
                ->with('success', 'Custom deduction deleted successfully.');
        }

        return redirect()->route('deductions.index', ['business_id' => $businessId])
            ->with('success', 'Custom deduction deleted successfully.');
    }
}
