<?php

namespace App\Http\Controllers;

use App\Models\Adjustment;
use App\Models\Business;
use App\Models\Employee;
use App\Models\PayrollSchedule;
use App\Services\AuditService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AdjustmentController extends Controller
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * Display a listing of adjustments for a business (company-wide)
     */
    public function index(Request $request): Response
    {
        $businessId = $request->get('business_id') ?? Auth::user()->current_business_id ?? session('current_business_id');

        if (! $businessId) {
            $businessId = Auth::user()->businesses()->first()?->id;
        }

        $query = Adjustment::where('business_id', $businessId)
            ->whereNull('employee_id'); // Only company-wide adjustments

        $adjustments = $query->latest()->paginate(15);
        $businesses = Auth::user()->businesses()->get();

        return Inertia::render('adjustments/index', [
            'adjustments' => $adjustments,
            'businesses' => $businesses,
            'selectedBusinessId' => $businessId,
        ]);
    }

    /**
     * Display adjustments for a specific employee
     */
    public function employeeIndex(Employee $employee): Response
    {
        $adjustments = Adjustment::where('business_id', $employee->business_id)
            ->where(function ($query) use ($employee) {
                $query->whereNull('employee_id') // Company-wide
                    ->orWhere('employee_id', $employee->id); // Employee-specific
            })
            ->where('is_active', true)
            ->latest()
            ->get();

        return Inertia::render('adjustments/employee-index', [
            'employee' => $employee->load('business'),
            'adjustments' => $adjustments,
        ]);
    }

    /**
     * Show the form for creating a new adjustment.
     */
    public function create(Request $request): Response
    {
        $businessId = $request->get('business_id') ?? Auth::user()->current_business_id ?? session('current_business_id');
        $employeeId = $request->get('employee_id');

        $businesses = Auth::user()->businesses()->get();
        $employee = $employeeId ? Employee::findOrFail($employeeId) : null;

        // Get active payroll schedules for the selected business
        // If employee is selected, only show schedules that include this employee
        $payrollSchedulesQuery = PayrollSchedule::where('business_id', $businessId)
            ->where('status', 'active');

        if ($employee) {
            // Only show schedules where this employee is assigned
            $payrollSchedulesQuery->whereHas('employees', function ($query) use ($employee) {
                $query->where('employees.id', $employee->id);
            });
        }

        $payrollSchedules = $businessId
            ? $payrollSchedulesQuery
                ->select(['id', 'name', 'schedule_type', 'next_run_at'])
                ->orderBy('name')
                ->get()
            : collect();

        return Inertia::render('adjustments/create', [
            'businesses' => $businesses,
            'selectedBusinessId' => $businessId,
            'employee' => $employee,
            'payrollSchedules' => $payrollSchedules,
        ]);
    }

    /**
     * Store a newly created adjustment.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'business_id' => 'required|exists:businesses,id',
            'employee_id' => 'nullable|exists:employees,id',
            'payroll_schedule_id' => 'nullable|exists:payroll_schedules,id',
            'name' => 'required|string|max:255',
            'type' => 'required|in:fixed,percentage',
            'amount' => 'required|numeric|min:0',
            'adjustment_type' => 'required|in:deduction,addition',
            'is_recurring' => 'boolean',
            'payroll_period_start' => 'nullable|date',
            'payroll_period_end' => 'nullable|date',
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
                ->withErrors(['business_id' => "Cannot create adjustment. Business is {$business->status}."])
                ->withInput();
        }

        // Validate percentage amount
        if ($validated['type'] === 'percentage' && $validated['amount'] > 100) {
            return back()
                ->withErrors(['amount' => 'Percentage cannot exceed 100%.'])
                ->withInput();
        }

        // Validate recurring vs once-off logic
        $isRecurring = $validated['is_recurring'] ?? true;

        if ($isRecurring) {
            // Recurring adjustments: payroll_period_start, payroll_period_end, and payroll_schedule_id must be null
            $validated['payroll_period_start'] = null;
            $validated['payroll_period_end'] = null;
            $validated['payroll_schedule_id'] = null;
        } else {
            // Once-off adjustments: payroll_schedule_id is required
            if (empty($validated['payroll_schedule_id'])) {
                return back()
                    ->withErrors(['payroll_schedule_id' => 'Payroll schedule is required for once-off adjustments.'])
                    ->withInput();
            }

            // Validate that payroll schedule belongs to the business
            $payrollSchedule = PayrollSchedule::findOrFail($validated['payroll_schedule_id']);
            if ($payrollSchedule->business_id !== $validated['business_id']) {
                return back()
                    ->withErrors(['payroll_schedule_id' => 'Payroll schedule does not belong to the selected business.'])
                    ->withInput();
            }

            // If employee is specified, validate that the payroll schedule includes this employee
            if ($validated['employee_id']) {
                $employeeInSchedule = $payrollSchedule->employees()->where('employees.id', $validated['employee_id'])->exists();
                if (! $employeeInSchedule) {
                    return back()
                        ->withErrors(['payroll_schedule_id' => 'The selected employee is not part of this payroll schedule.'])
                        ->withInput();
                }

                // For employee-specific once-off adjustments: AUTO-CALCULATE period from schedule
                // This ensures the period always matches what the schedule will actually process
                // and prevents ambiguity when multiple schedules run in the same month
                $calculatedPeriod = $payrollSchedule->calculatePayPeriod();
                $validated['payroll_period_start'] = $calculatedPeriod['start']->format('Y-m-d');
                $validated['payroll_period_end'] = $calculatedPeriod['end']->format('Y-m-d');

                // Check for duplicate employee-specific once-off adjustment
                // Same employee + same schedule + same period = duplicate
                $exists = Adjustment::where('business_id', $validated['business_id'])
                    ->where('employee_id', $validated['employee_id'])
                    ->where('payroll_schedule_id', $validated['payroll_schedule_id'])
                    ->where('period_start', $validated['payroll_period_start'])
                    ->where('period_end', $validated['payroll_period_end'])
                    ->whereNotNull('period_start')
                    ->whereNotNull('period_end')
                    ->exists();

                if ($exists) {
                    return back()
                        ->withErrors(['payroll_schedule_id' => 'A once-off adjustment already exists for this employee, schedule, and period combination.'])
                        ->withInput();
                }
            } else {
                // Company-wide once-off adjustments: user must specify period manually
                if (empty($validated['payroll_period_start']) || empty($validated['payroll_period_end'])) {
                    return back()
                        ->withErrors(['payroll_period_start' => 'Payroll period dates are required for once-off adjustments.'])
                        ->withInput();
                }

                // Ensure start <= end
                $periodStart = Carbon::parse($validated['payroll_period_start']);
                $periodEnd = Carbon::parse($validated['payroll_period_end']);

                if ($periodStart->gt($periodEnd)) {
                    return back()
                        ->withErrors(['payroll_period_start' => 'Payroll period start date must be before or equal to end date.'])
                        ->withInput();
                }
            }
        }

        // Set default is_active to true if not provided
        if (! isset($validated['is_active'])) {
            $validated['is_active'] = true;
        }

        $createAttributes = [
            'business_id' => $validated['business_id'],
            'employee_id' => $validated['employee_id'] ?? null,
            'payroll_schedule_id' => $validated['payroll_schedule_id'] ?? null,
            'name' => $validated['name'],
            'type' => $validated['type'],
            'amount' => $validated['amount'],
            'adjustment_type' => $validated['adjustment_type'],
            'period_start' => $validated['payroll_period_start'] ?? null,
            'period_end' => $validated['payroll_period_end'] ?? null,
            'is_active' => $validated['is_active'],
            'description' => $validated['description'] ?? null,
        ];

        $adjustment = DB::transaction(function () use ($createAttributes) {
            $adjustment = Adjustment::create($createAttributes);
            $this->auditService->log('adjustment.created', $adjustment, $adjustment->getAttributes());

            return $adjustment;
        });

        if ($validated['employee_id']) {
            return redirect()->route('employees.edit', $validated['employee_id'])
                ->with('success', 'Adjustment created successfully.');
        }

        return redirect()->route('adjustments.index', ['business_id' => $validated['business_id']])
            ->with('success', 'Adjustment created successfully.');
    }

    /**
     * Show the form for editing the specified adjustment.
     */
    public function edit(Adjustment $adjustment): Response
    {
        $businesses = Auth::user()->businesses()->get();
        $employee = $adjustment->employee;

        // Get active payroll schedules for the adjustment's business
        // If adjustment is employee-specific, only show schedules that include this employee
        $payrollSchedulesQuery = PayrollSchedule::where('business_id', $adjustment->business_id)
            ->where('status', 'active');

        if ($adjustment->employee_id) {
            // Only show schedules where this employee is assigned
            $payrollSchedulesQuery->whereHas('employees', function ($query) use ($adjustment) {
                $query->where('employees.id', $adjustment->employee_id);
            });
        }

        $payrollSchedules = $payrollSchedulesQuery
            ->select(['id', 'name', 'schedule_type', 'next_run_at'])
            ->orderBy('name')
            ->get();

        return Inertia::render('adjustments/edit', [
            'adjustment' => $adjustment->load(['business', 'employee']),
            'businesses' => $businesses,
            'employee' => $employee,
            'payrollSchedules' => $payrollSchedules,
        ]);
    }

    /**
     * Update the specified adjustment.
     */
    public function update(Request $request, Adjustment $adjustment)
    {
        $validated = $request->validate([
            'business_id' => 'required|exists:businesses,id',
            'employee_id' => 'nullable|exists:employees,id',
            'payroll_schedule_id' => 'nullable|exists:payroll_schedules,id',
            'name' => 'required|string|max:255',
            'type' => 'required|in:fixed,percentage',
            'amount' => 'required|numeric|min:0',
            'adjustment_type' => 'required|in:deduction,addition',
            'is_recurring' => 'boolean',
            'payroll_period_start' => 'nullable|date',
            'payroll_period_end' => 'nullable|date',
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

        // Validate recurring vs once-off logic
        $isRecurring = $validated['is_recurring'] ?? true;

        if ($isRecurring) {
            // Recurring adjustments: payroll_period_start, payroll_period_end, and payroll_schedule_id must be null
            $validated['payroll_period_start'] = null;
            $validated['payroll_period_end'] = null;
            $validated['payroll_schedule_id'] = null;
        } else {
            // Once-off adjustments: payroll_schedule_id is required
            if (empty($validated['payroll_schedule_id'])) {
                return back()
                    ->withErrors(['payroll_schedule_id' => 'Payroll schedule is required for once-off adjustments.'])
                    ->withInput();
            }

            // Validate that payroll schedule belongs to the business
            $payrollSchedule = PayrollSchedule::findOrFail($validated['payroll_schedule_id']);
            if ($payrollSchedule->business_id !== $validated['business_id']) {
                return back()
                    ->withErrors(['payroll_schedule_id' => 'Payroll schedule does not belong to the selected business.'])
                    ->withInput();
            }

            // If employee is specified, validate that the payroll schedule includes this employee
            if ($validated['employee_id']) {
                $employeeInSchedule = $payrollSchedule->employees()->where('employees.id', $validated['employee_id'])->exists();
                if (! $employeeInSchedule) {
                    return back()
                        ->withErrors(['payroll_schedule_id' => 'The selected employee is not part of this payroll schedule.'])
                        ->withInput();
                }

                // For employee-specific once-off adjustments: AUTO-CALCULATE period from schedule
                $calculatedPeriod = $payrollSchedule->calculatePayPeriod();
                $validated['payroll_period_start'] = $calculatedPeriod['start']->format('Y-m-d');
                $validated['payroll_period_end'] = $calculatedPeriod['end']->format('Y-m-d');

                // Check for duplicate employee-specific once-off adjustment (exclude current adjustment)
                $exists = Adjustment::where('business_id', $validated['business_id'])
                    ->where('employee_id', $validated['employee_id'])
                    ->where('payroll_schedule_id', $validated['payroll_schedule_id'])
                    ->where('period_start', $validated['payroll_period_start'])
                    ->where('period_end', $validated['payroll_period_end'])
                    ->whereNotNull('period_start')
                    ->whereNotNull('period_end')
                    ->where('id', '!=', $adjustment->id)
                    ->exists();

                if ($exists) {
                    return back()
                        ->withErrors(['payroll_schedule_id' => 'A once-off adjustment already exists for this employee, schedule, and period combination.'])
                        ->withInput();
                }
            } else {
                // Company-wide once-off adjustments: user must specify period manually
                if (empty($validated['payroll_period_start']) || empty($validated['payroll_period_end'])) {
                    return back()
                        ->withErrors(['payroll_period_start' => 'Payroll period dates are required for once-off adjustments.'])
                        ->withInput();
                }

                // Ensure start <= end
                $periodStart = Carbon::parse($validated['payroll_period_start']);
                $periodEnd = Carbon::parse($validated['payroll_period_end']);

                if ($periodStart->gt($periodEnd)) {
                    return back()
                        ->withErrors(['payroll_period_start' => 'Payroll period start date must be before or equal to end date.'])
                        ->withInput();
                }
            }
        }

        DB::transaction(function () use ($validated, $adjustment) {
            $adjustment->update($validated);
            $this->auditService->log('adjustment.updated', $adjustment, [
                'old' => $adjustment->getOriginal(),
                'new' => $adjustment->getChanges(),
            ]);
        });

        if ($validated['employee_id']) {
            return redirect()->route('employees.edit', $validated['employee_id'])
                ->with('success', 'Adjustment updated successfully.');
        }

        return redirect()->route('adjustments.index', ['business_id' => $validated['business_id']])
            ->with('success', 'Adjustment updated successfully.');
    }

    /**
     * Remove the specified adjustment.
     */
    public function destroy(Adjustment $adjustment)
    {
        $businessId = $adjustment->business_id;
        $employeeId = $adjustment->employee_id;

        $this->auditService->log('adjustment.deleted', $adjustment, $adjustment->getAttributes());

        $adjustment->delete();

        if ($employeeId) {
            return redirect()->route('employees.edit', $employeeId)
                ->with('success', 'Adjustment deleted successfully.');
        }

        return redirect()->route('adjustments.index', ['business_id' => $businessId])
            ->with('success', 'Adjustment deleted successfully.');
    }

    /**
     * Calculate the pay period for a given payroll schedule.
     *
     * This endpoint is used by the frontend to auto-fill period dates
     * when creating employee-specific once-off adjustments.
     */
    public function calculatePeriod(Request $request)
    {
        $validated = $request->validate([
            'payroll_schedule_id' => 'required|exists:payroll_schedules,id',
        ]);

        $schedule = PayrollSchedule::findOrFail($validated['payroll_schedule_id']);
        $period = $schedule->calculatePayPeriod();

        return response()->json([
            'payroll_period_start' => $period['start']->format('Y-m-d'),
            'payroll_period_end' => $period['end']->format('Y-m-d'),
            'schedule_name' => $schedule->name,
            'schedule_type' => $schedule->schedule_type,
        ]);
    }
}
