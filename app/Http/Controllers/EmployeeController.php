<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\Employee;
use App\Services\AdjustmentService;
use App\Services\AuditService;
use App\Services\SouthAfricanTaxService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeController extends Controller
{
    public function __construct(
        protected AuditService $auditService,
        protected SouthAfricanTaxService $taxService,
        protected AdjustmentService $adjustmentService
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
            'adjustments' => function ($query) {
                $query->where('is_active', true);
            },
            'timeEntries' => function ($query) {
                $query->whereNotNull('sign_out_time');
            },
        ]);

        // For preview, use current month period and get all valid adjustments (recurring + once-off for current period)
        $periodStart = Carbon::parse(now()->startOfMonth());
        $periodEnd = Carbon::parse(now()->endOfMonth());
        $adjustments = $this->adjustmentService->getValidAdjustments($employee, $periodStart, $periodEnd);

        // Calculate net salary after statutory deductions
        $uifExempt = $employee->isUIFExempt();
        $taxBreakdown = $this->taxService->calculateNetSalary($employee->gross_salary, [
            'uif_exempt' => $uifExempt,
        ]);

        // Apply adjustments for preview
        $adjustmentResult = $this->adjustmentService->applyAdjustments(
            $taxBreakdown['net'],
            $adjustments,
            $employee->gross_salary
        );

        // Merge adjustment results into tax breakdown for frontend
        $taxBreakdown['adjustments'] = $adjustmentResult['adjustments_breakdown'];
        $taxBreakdown['total_adjustments'] = $adjustmentResult['total_adjustments'];
        $taxBreakdown['final_net_salary'] = $adjustmentResult['final_net_salary'];

        return Inertia::render('employees/edit', [
            'employee' => $employee,
            'businesses' => $businesses,
            'taxBreakdown' => $taxBreakdown,
            'adjustments' => $adjustments,
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
        // Use current month period for preview calculations
        $periodStart = Carbon::parse(now()->startOfMonth());
        $periodEnd = Carbon::parse(now()->endOfMonth());

        // If employee is provided, use their gross_salary; otherwise get it from request
        if ($employee) {
            $grossSalary = $employee->gross_salary;
            $employeeId = $employee->id;
            $businessId = $employee->business_id;
            $uifExempt = $employee->isUIFExempt();
            $adjustments = $this->adjustmentService->getValidAdjustments($employee, $periodStart, $periodEnd);
        } else {
            // Get gross_salary from request (works with both JSON and form data)
            $grossSalary = $request->input('gross_salary') ?? $request->json('gross_salary');

            if (! $grossSalary || ! is_numeric($grossSalary) || $grossSalary <= 0) {
                return response()->json(['error' => 'Gross salary is required and must be greater than 0'], 400);
            }

            $grossSalary = (float) $grossSalary;

            // Get business_id and employee_id
            $businessId = $request->input('business_id') ?? $request->json('business_id');
            $employeeId = $request->input('employee_id') ?? $request->json('employee_id');

            $uifExempt = false;
            $adjustments = collect();

            if ($businessId && $employeeId) {
                $employee = Employee::find($employeeId);
                if ($employee && $employee->business_id === $businessId) {
                    $uifExempt = $employee->isUIFExempt();
                    $adjustments = $this->adjustmentService->getValidAdjustments($employee, $periodStart, $periodEnd);
                }
            }
        }

        // Calculate net salary after statutory deductions
        $breakdown = $this->taxService->calculateNetSalary($grossSalary, [
            'uif_exempt' => $uifExempt,
        ]);

        // Apply adjustments
        $adjustmentResult = $this->adjustmentService->applyAdjustments(
            $breakdown['net'],
            $adjustments,
            $grossSalary
        );

        // Merge results
        $breakdown['adjustments'] = $adjustmentResult['adjustments_breakdown'];
        $breakdown['total_adjustments'] = $adjustmentResult['total_adjustments'];
        $breakdown['final_net_salary'] = $adjustmentResult['final_net_salary'];

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

    /**
     * Search employees by name or email (for autocomplete/search)
     */
    public function search(Request $request)
    {
        $request->validate([
            'business_id' => 'required|exists:businesses,id',
            'query' => 'nullable|string|max:255',
        ]);

        $user = Auth::user();
        $businessId = $request->business_id;

        // Verify user has access to this business
        $hasAccess = $user->ownedBusinesses()->where('businesses.id', $businessId)->exists()
            || $user->businesses()->where('businesses.id', $businessId)->exists();

        if (! $hasAccess) {
            return response()->json(['error' => 'Unauthorized access to business.'], 403);
        }

        $query = Employee::query()
            ->where('business_id', $businessId)
            ->select(['id', 'business_id', 'name', 'email']);

        if ($request->filled('query')) {
            $searchTerm = $request->get('query');
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'like', "%{$searchTerm}%")
                    ->orWhere('email', 'like', "%{$searchTerm}%");
            });
        }

        $employees = $query
            ->orderBy('name')
            ->limit(20)
            ->get();

        return response()->json($employees);
    }

    /**
     * Display benefits for a specific employee.
     * Shows company benefits (read-only) and employee-specific overrides.
     */
    public function benefits(Employee $employee): Response
    {
        // Get all company-wide recurring benefits (these apply to everyone)
        $companyBenefits = \App\Models\Adjustment::where('business_id', $employee->business_id)
            ->whereNull('employee_id')
            ->whereNull('period_start')
            ->whereNull('period_end')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        // Get employee-specific recurring adjustments (overrides)
        $employeeOverrides = \App\Models\Adjustment::where('business_id', $employee->business_id)
            ->where('employee_id', $employee->id)
            ->whereNull('period_start')
            ->whereNull('period_end')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        // Match overrides with company benefits by name
        $benefitsWithOverrides = $companyBenefits->map(function ($benefit) use ($employeeOverrides) {
            $override = $employeeOverrides->firstWhere('name', $benefit->name);

            return [
                'company_benefit' => $benefit,
                'override' => $override,
                'effective_amount' => $override ? $override->amount : $benefit->amount,
                'has_override' => $override !== null,
            ];
        });

        // Get overrides that don't match any company benefit (employee-specific only)
        $employeeOnlyBenefits = $employeeOverrides->reject(function ($override) use ($companyBenefits) {
            return $companyBenefits->contains('name', $override->name);
        });

        return Inertia::render('employees/benefits', [
            'employee' => $employee->load('business'),
            'companyBenefits' => $companyBenefits,
            'benefitsWithOverrides' => $benefitsWithOverrides,
            'employeeOnlyBenefits' => $employeeOnlyBenefits,
        ]);
    }

    /**
     * Create or update an employee benefit override.
     * Creates an employee-specific adjustment that overrides a company benefit.
     */
    public function overrideBenefit(Request $request, Employee $employee)
    {
        $validated = $request->validate([
            'benefit_id' => 'required|exists:adjustments,id', // The company benefit to override
            'amount' => 'required|numeric|min:0',
            'period_start' => 'nullable|date', // null = forever, set = specific period
            'period_end' => 'nullable|date', // null = forever, set = specific period
            'description' => 'nullable|string',
        ]);

        // Get the company benefit
        $companyBenefit = \App\Models\Adjustment::findOrFail($validated['benefit_id']);

        // Ensure it's a company-wide recurring benefit
        if ($companyBenefit->employee_id !== null || $companyBenefit->period_start !== null || $companyBenefit->period_end !== null) {
            return back()
                ->withErrors(['benefit_id' => 'Selected benefit is not a company-wide recurring benefit.'])
                ->withInput();
        }

        // Ensure it belongs to the same business
        if ($companyBenefit->business_id !== $employee->business_id) {
            return back()
                ->withErrors(['benefit_id' => 'Benefit does not belong to the same business.'])
                ->withInput();
        }

        // Validate percentage amount if benefit is percentage type
        if ($companyBenefit->type === 'percentage' && $validated['amount'] > 100) {
            return back()
                ->withErrors(['amount' => 'Percentage cannot exceed 100%.'])
                ->withInput();
        }

        // Validate period if provided
        if ($validated['period_start'] !== null && $validated['period_end'] !== null) {
            $periodStart = Carbon::parse($validated['period_start']);
            $periodEnd = Carbon::parse($validated['period_end']);

            if ($periodStart->gt($periodEnd)) {
                return back()
                    ->withErrors(['period_start' => 'Start date must be before or equal to end date.'])
                    ->withInput();
            }
        } else {
            // If one is null, both must be null (forever)
            $validated['period_start'] = null;
            $validated['period_end'] = null;
        }

        // Check if override already exists
        $existingOverride = \App\Models\Adjustment::where('business_id', $employee->business_id)
            ->where('employee_id', $employee->id)
            ->where('name', $companyBenefit->name)
            ->whereNull('period_start')
            ->whereNull('period_end')
            ->first();

        DB::transaction(function () use ($validated, $employee, $companyBenefit, $existingOverride) {
            if ($existingOverride) {
                // Update existing override
                $existingOverride->update([
                    'amount' => $validated['amount'],
                    'description' => $validated['description'] ?? $existingOverride->description,
                ]);

                $this->auditService->log('benefit.override_updated', $existingOverride, [
                    'old' => $existingOverride->getOriginal(),
                    'new' => $existingOverride->getChanges(),
                    'company_benefit_id' => $companyBenefit->id,
                ]);
            } else {
                // Create new override
                $override = \App\Models\Adjustment::create([
                    'business_id' => $employee->business_id,
                    'employee_id' => $employee->id,
                    'name' => $companyBenefit->name,
                    'type' => $companyBenefit->type,
                    'amount' => $validated['amount'],
                    'adjustment_type' => $companyBenefit->adjustment_type,
                    'period_start' => $validated['period_start'],
                    'period_end' => $validated['period_end'],
                    'is_active' => true,
                    'description' => $validated['description'] ?? "Override for {$companyBenefit->name}",
                ]);

                $this->auditService->log('benefit.override_created', $override, [
                    'company_benefit_id' => $companyBenefit->id,
                    'company_benefit_amount' => $companyBenefit->amount,
                    'override_amount' => $validated['amount'],
                ]);
            }
        });

        return redirect()->route('employees.benefits', $employee->id)
            ->with('success', "{$companyBenefit->name} override created. Employee will receive {$validated['amount']} instead of {$companyBenefit->amount}.");
    }

    /**
     * Remove an employee benefit override.
     */
    public function removeOverride(Employee $employee, \App\Models\Adjustment $override)
    {
        // Ensure override belongs to this employee
        if ($override->employee_id !== $employee->id || $override->business_id !== $employee->business_id) {
            abort(404, 'Override not found for this employee.');
        }

        // Ensure it's a recurring override (period is null)
        if ($override->period_start !== null || $override->period_end !== null) {
            abort(404, 'This is not a benefit override.');
        }

        $this->auditService->log('benefit.override_removed', $override, $override->getAttributes());

        $override->delete();

        return redirect()->route('employees.benefits', $employee->id)
            ->with('success', 'Benefit override removed. Employee will now receive the company benefit rate.');
    }
}
