<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\Employee;
use App\Services\AuditService;
use App\Services\SouthAfricanTaxService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeController extends Controller
{
    public function __construct(
        protected AuditService $auditService,
        protected SouthAfricanTaxService $taxService
    ) {
    }

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

        $employees = $query->with('business')->latest()->paginate(15);

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
            'gross_salary' => 'required|numeric|min:0.01',
            'bank_account_details' => 'nullable|array',
            'tax_status' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        // Check if business is active
        $business = Business::findOrFail($validated['business_id']);
        if (!$business->canPerformActions()) {
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

        // Calculate tax breakdown for preview
        $taxBreakdown = $this->taxService->calculateNetSalary($employee->gross_salary);

        return Inertia::render('employees/edit', [
            'employee' => $employee->load('business'),
            'businesses' => $businesses,
            'taxBreakdown' => $taxBreakdown,
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
            'department' => 'nullable|string|max:255',
            'start_date' => 'nullable|date',
            'gross_salary' => 'required|numeric|min:0.01',
            'bank_account_details' => 'nullable|array',
            'tax_status' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        // Check if business is active
        $business = Business::findOrFail($validated['business_id']);
        if (!$business->canPerformActions()) {
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
     */
    public function calculateTax(Request $request, ?Employee $employee = null)
    {
        // If employee is provided, use their gross_salary; otherwise get it from request
        if ($employee) {
            $grossSalary = $employee->gross_salary;
        } else {
            // Get gross_salary from request (works with both JSON and form data)
            $grossSalary = $request->input('gross_salary') ?? $request->json('gross_salary');
            
            if (!$grossSalary || !is_numeric($grossSalary) || $grossSalary <= 0) {
                return response()->json(['error' => 'Gross salary is required and must be greater than 0'], 400);
            }
            
            $grossSalary = (float) $grossSalary;
        }

        $breakdown = $this->taxService->calculateNetSalary($grossSalary);

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
