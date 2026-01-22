<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\Employee;
use App\Services\AuditService;
use App\Services\SouthAfricanTaxService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeController extends Controller
{
    public function __construct(
        protected AuditService $auditService,
        protected SouthAfricanTaxService $taxService
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        $businessId = $request->get('business_id') ?? Auth::user()->current_business_id ?? session('current_business_id');

        $query = Employee::query();

        if ($businessId) {
            $query->where('business_id', $businessId);
        } else {
            // Get all businesses user has access to
            $userBusinessIds = Auth::user()->businesses()->pluck('businesses.id');
            $query->whereIn('business_id', $userBusinessIds);
        }

        $employees = $query
            ->select([
                'id',
                'business_id',
                'name',
                'email',
                'employment_type',
                'department',
                'gross_salary',
                'hourly_rate',
                'created_at',
                'updated_at',
            ])
            ->with('business:id,name')
            ->latest()
            ->paginate(15);

        return Inertia::render('employees/index', [
            'employees' => $employees,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request): Response
    {
        $businessId = $request->get('business_id') ?? Auth::user()->current_business_id ?? session('current_business_id');
        $businesses = Auth::user()->businesses()->get();

        return Inertia::render('employees/create', [
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
            'id_number' => 'nullable|string|max:255',
            'tax_number' => 'nullable|string|max:255',
            'employment_type' => 'required|in:full_time,part_time,contract',
            'department' => 'nullable|string|max:255',
            'start_date' => 'nullable|date',
            'gross_salary' => 'nullable|numeric|min:0.01',
            'hourly_rate' => 'nullable|numeric|min:0.01',
            'overtime_rate_multiplier' => 'nullable|numeric|min:1',
            'weekend_rate_multiplier' => 'nullable|numeric|min:1',
            'holiday_rate_multiplier' => 'nullable|numeric|min:1',
            'bank_account_details' => 'nullable|array',
            'tax_status' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        // Ensure either gross_salary or hourly_rate is provided
        if (empty($validated['gross_salary']) && empty($validated['hourly_rate'])) {
            return back()
                ->withErrors(['gross_salary' => 'Either gross salary or hourly rate must be provided.'])
                ->withInput();
        }

        // Check if business is active
        $business = Business::findOrFail($validated['business_id']);
        if (! $business->canPerformActions()) {
            return back()
                ->withErrors(['business_id' => "Cannot create employee. Business is {$business->status}."])
                ->withInput();
        }

        // Wrap all database operations in a transaction
        $employee = DB::transaction(function () use ($validated) {
            $employee = Employee::create($validated);
            $this->auditService->log('employee.created', $employee, $employee->getAttributes());

            return $employee;
        });

        return redirect()->route('employees.index')
            ->with('success', 'Employee created successfully.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Employee $employee): Response
    {
        $businesses = Auth::user()->businesses()->get();

        // Eager load relationships
        $employee->load([
            'business',
            'customDeductions' => function ($query) {
                $query->where('is_active', true);
            },
            'timeEntries' => function ($query) {
                $query->whereNotNull('sign_out_time');
            },
        ]);

        // Get all deductions for this employee (company-wide + employee-specific)
        $customDeductions = $employee->getAllDeductions();

        // Calculate tax breakdown for preview with custom deductions
        // Check if employee is exempt from UIF (works < 24 hours/month)
        $taxBreakdown = $this->taxService->calculateNetSalary($employee->gross_salary, [
            'custom_deductions' => $customDeductions,
            'uif_exempt' => $employee->isUIFExempt(),
        ]);

        return Inertia::render('employees/edit', [
            'employee' => $employee,
            'businesses' => $businesses,
            'taxBreakdown' => $taxBreakdown,
            'customDeductions' => $customDeductions,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Employee $employee)
    {
        $validated = $request->validate([
            'business_id' => 'required|exists:businesses,id',
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'id_number' => 'nullable|string|max:255',
            'tax_number' => 'nullable|string|max:255',
            'employment_type' => 'required|in:full_time,part_time,contract',
            'hours_worked_per_month' => 'nullable|numeric|min:0|max:744', // Max 24 hours/day * 31 days
            'department' => 'nullable|string|max:255',
            'start_date' => 'nullable|date',
            'gross_salary' => 'nullable|numeric|min:0.01',
            'hourly_rate' => 'nullable|numeric|min:0.01',
            'overtime_rate_multiplier' => 'nullable|numeric|min:1',
            'weekend_rate_multiplier' => 'nullable|numeric|min:1',
            'holiday_rate_multiplier' => 'nullable|numeric|min:1',
            'bank_account_details' => 'nullable|array',
            'tax_status' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        // Ensure either gross_salary or hourly_rate is provided
        if (empty($validated['gross_salary']) && empty($validated['hourly_rate'])) {
            return back()
                ->withErrors(['gross_salary' => 'Either gross salary or hourly rate must be provided.'])
                ->withInput();
        }

        // Check if business is active
        $business = Business::findOrFail($validated['business_id']);
        if (! $business->canPerformActions()) {
            return back()
                ->withErrors(['business_id' => "Cannot update employee. Business is {$business->status}."])
                ->withInput();
        }

        // Wrap all database operations in a transaction
        DB::transaction(function () use ($validated, $employee) {
            $employee->update($validated);
            $this->auditService->log('employee.updated', $employee, [
                'old' => $employee->getOriginal(),
                'new' => $employee->getChanges(),
            ]);
        });

        return redirect()->route('employees.index')
            ->with('success', 'Employee updated successfully.');
    }

    /**
     * Calculate tax breakdown for preview (AJAX endpoint)
     * Results are cached for 60 seconds to improve performance for repeated calculations
     */
    public function calculateTax(Request $request, ?Employee $employee = null)
    {
        // If employee is provided, use their gross_salary; otherwise get it from request
        if ($employee) {
            $grossSalary = $employee->gross_salary;
            $customDeductions = $employee->getAllDeductions();
            $employeeId = $employee->id;
            $businessId = $employee->business_id;
            $uifExempt = $employee->isUIFExempt();
        } else {
            // Get gross_salary from request (works with both JSON and form data)
            $grossSalary = $request->input('gross_salary') ?? $request->json('gross_salary');

            if (! $grossSalary || ! is_numeric($grossSalary) || $grossSalary <= 0) {
                return response()->json(['error' => 'Gross salary is required and must be greater than 0'], 400);
            }

            $grossSalary = (float) $grossSalary;

            // Get business_id to fetch company-wide deductions
            $businessId = $request->input('business_id') ?? $request->json('business_id');
            $employeeId = $request->input('employee_id') ?? $request->json('employee_id');

            $customDeductions = collect();
            $uifExempt = false;
            if ($businessId) {
                // Company-wide deductions
                $companyDeductions = \App\Models\CustomDeduction::where('business_id', $businessId)
                    ->whereNull('employee_id')
                    ->where('is_active', true)
                    ->get();
                $customDeductions = $customDeductions->merge($companyDeductions);

                // Employee-specific deductions and UIF exemption check
                if ($employeeId) {
                    $employee = \App\Models\Employee::find($employeeId);
                    if ($employee) {
                        $uifExempt = $employee->isUIFExempt();
                        $employeeDeductions = \App\Models\CustomDeduction::where('business_id', $businessId)
                            ->where('employee_id', $employeeId)
                            ->where('is_active', true)
                            ->get();
                        $customDeductions = $customDeductions->merge($employeeDeductions);
                    }
                }
            }
        }

        // Create cache key based on input parameters
        $deductionIds = $customDeductions->pluck('id')->sort()->implode(',');
        $cacheKey = "tax_calculation_{$grossSalary}_{$businessId}_{$employeeId}_{$uifExempt}_{$deductionIds}";

        // Check cache first (60 second TTL)
        $breakdown = Cache::remember($cacheKey, 60, function () use ($grossSalary, $customDeductions, $uifExempt) {
            return $this->taxService->calculateNetSalary($grossSalary, [
                'custom_deductions' => $customDeductions,
                'uif_exempt' => $uifExempt,
            ]);
        });

        return response()->json($breakdown);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Employee $employee)
    {
        $this->auditService->log('employee.deleted', $employee, $employee->getAttributes());

        $employee->delete();

        return redirect()->route('employees.index')
            ->with('success', 'Employee deleted successfully.');
    }
}
