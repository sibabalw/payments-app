<?php

namespace App\Http\Controllers;

use App\Models\Adjustment;
use App\Models\Business;
use App\Models\Employee;
use App\Services\AdjustmentService;
use App\Services\AuditService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PaymentsController extends Controller
{
    public function __construct(
        protected AuditService $auditService,
        protected AdjustmentService $adjustmentService
    ) {}

    /**
     * Display a listing of payments (one-off adjustments)
     * Shows both company-wide and employee-specific payments
     */
    public function index(Request $request): Response
    {
        $businessId = $request->get('business_id') ?? Auth::user()->current_business_id ?? session('current_business_id');
        $filter = $request->get('filter', 'all'); // all, company, employee
        $period = $request->get('period'); // Optional period filter

        if (! $businessId) {
            $businessId = Auth::user()->businesses()->first()?->id;
        }

        $businesses = Auth::user()->businesses()->get();

        // If no business selected and user has businesses, use first one
        if (! $businessId && $businesses->isNotEmpty()) {
            $businessId = $businesses->first()->id;
        }

        $query = Adjustment::query();
        
        // Only filter by business if we have one
        if ($businessId) {
            $query->where('business_id', $businessId);
        } else {
            // If no business, return empty results
            $query->whereRaw('1 = 0');
        }
        
        $query->whereNotNull('period_start') // One-off payments only
            ->whereNotNull('period_end');

        // Apply filters
        if ($filter === 'company') {
            $query->whereNull('employee_id');
        } elseif ($filter === 'employee') {
            $query->whereNotNull('employee_id');
        }

        // Period filter
        if ($period) {
            $periodStart = Carbon::parse($period)->startOfMonth();
            $periodEnd = Carbon::parse($period)->endOfMonth();
            $query->where(function ($q) use ($periodStart, $periodEnd) {
                $q->where('period_start', '<=', $periodEnd)
                    ->where('period_end', '>=', $periodStart);
            });
        }

        $payments = $query->with('employee:id,name')
            ->latest()
            ->paginate(15);

        return Inertia::render('payments/index', [
            'payments' => $payments,
            'businesses' => $businesses ?? collect(),
            'selectedBusinessId' => $businessId,
            'filters' => [
                'filter' => $filter,
                'period' => $period,
            ],
        ]);
    }

    /**
     * Show the form for creating a new payment.
     * Smart scope detection: if employee_id in request, pre-fill it
     */
    public function create(Request $request): Response
    {
        $businessId = $request->get('business_id') ?? Auth::user()->current_business_id ?? session('current_business_id');
        $employeeId = $request->get('employee_id'); // Optional - from context

        $businesses = Auth::user()->businesses()->get();
        $employee = $employeeId ? Employee::findOrFail($employeeId) : null;

        // Get employees for the business (for multi-select if company-wide)
        $employees = $businessId
            ? Employee::where('business_id', $businessId)
                ->select(['id', 'name', 'email'])
                ->orderBy('name')
                ->get()
            : collect();

        return Inertia::render('payments/create', [
            'businesses' => $businesses,
            'selectedBusinessId' => $businessId,
            'employee' => $employee,
            'employees' => $employees,
        ]);
    }

    /**
     * Store a newly created payment.
     * Smart scope detection: if employee_id provided = employee-specific, else company-wide
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'business_id' => 'required|exists:businesses,id',
            'employee_id' => 'nullable|exists:employees,id',
            'employee_ids' => 'nullable|array', // For "select employees" option
            'employee_ids.*' => 'exists:employees,id',
            'name' => 'required|string|max:255',
            'type' => 'required|in:fixed,percentage',
            'amount' => 'required|numeric|min:0',
            'adjustment_type' => 'required|in:deduction,addition',
            'period_start' => 'required|date',
            'period_end' => 'required|date',
            'is_active' => 'boolean',
            'description' => 'nullable|string',
        ]);

        // Check if business is active
        $business = Business::findOrFail($validated['business_id']);
        if (! $business->canPerformActions()) {
            return back()
                ->withErrors(['business_id' => "Cannot create payment. Business is {$business->status}."])
                ->withInput();
        }

        // Validate percentage amount
        if ($validated['type'] === 'percentage' && $validated['amount'] > 100) {
            return back()
                ->withErrors(['amount' => 'Percentage cannot exceed 100%.'])
                ->withInput();
        }

        // Ensure start <= end
        $periodStart = Carbon::parse($validated['period_start']);
        $periodEnd = Carbon::parse($validated['period_end']);

        if ($periodStart->gt($periodEnd)) {
            return back()
                ->withErrors(['period_start' => 'Start date must be before or equal to end date.'])
                ->withInput();
        }

        // Validate period is current or upcoming month only
        $currentMonthStart = now()->startOfMonth();
        $nextMonthEnd = now()->addMonth()->endOfMonth();

        if ($periodStart->lt($currentMonthStart) || $periodEnd->gt($nextMonthEnd)) {
            return back()
                ->withErrors(['period_start' => 'Payment period must be within current month or next month only.'])
                ->withInput();
        }

        // Set default is_active to true if not provided
        if (! isset($validated['is_active'])) {
            $validated['is_active'] = true;
        }

        // Handle scope: single employee, multiple employees, or all employees
        $employeeIds = [];
        if (! empty($validated['employee_ids'])) {
            // Multiple employees selected
            $employeeIds = $validated['employee_ids'];
        } elseif (! empty($validated['employee_id'])) {
            // Single employee
            $employeeIds = [$validated['employee_id']];
        }
        // If neither, employee_id will be null = company-wide

        $createdPayments = [];

        DB::transaction(function () use ($validated, $employeeIds, &$createdPayments) {
            if (empty($employeeIds)) {
                // Company-wide payment
                $payment = Adjustment::create([
                    'business_id' => $validated['business_id'],
                    'employee_id' => null,
                    'name' => $validated['name'],
                    'type' => $validated['type'],
                    'amount' => $validated['amount'],
                    'adjustment_type' => $validated['adjustment_type'],
                    'period_start' => $validated['period_start'],
                    'period_end' => $validated['period_end'],
                    'is_active' => $validated['is_active'],
                    'description' => $validated['description'],
                ]);

                $this->auditService->log('payment.created', $payment, $payment->getAttributes());
                $createdPayments[] = $payment;
            } else {
                // Employee-specific payments (one per employee)
                foreach ($employeeIds as $employeeId) {
                    // Validate employee belongs to business
                    $employee = Employee::findOrFail($employeeId);
                    if ($employee->business_id !== $validated['business_id']) {
                        continue; // Skip invalid employee
                    }

                    $payment = Adjustment::create([
                        'business_id' => $validated['business_id'],
                        'employee_id' => $employeeId,
                        'name' => $validated['name'],
                        'type' => $validated['type'],
                        'amount' => $validated['amount'],
                        'adjustment_type' => $validated['adjustment_type'],
                        'period_start' => $validated['period_start'],
                        'period_end' => $validated['period_end'],
                        'is_active' => $validated['is_active'],
                        'description' => $validated['description'],
                    ]);

                    $this->auditService->log('payment.created', $payment, $payment->getAttributes());
                    $createdPayments[] = $payment;
                }
            }
        });

        if (count($createdPayments) === 1 && ! empty($employeeIds)) {
            // Single employee payment - redirect to employee page
            return redirect()->route('employees.edit', $employeeIds[0])
                ->with('success', 'Payment created successfully.');
        }

        return redirect()->route('bonuses.index', ['business_id' => $validated['business_id']])
            ->with('success', 'Payment(s) created successfully.');
    }

    /**
     * Display payments for a specific employee
     */
    public function employeeIndex(Employee $employee): Response
    {
        $payments = Adjustment::where('business_id', $employee->business_id)
            ->where(function ($query) use ($employee) {
                // Company-wide payments OR employee-specific payments
                $query->whereNull('employee_id')
                    ->orWhere('employee_id', $employee->id);
            })
            ->whereNotNull('period_start') // One-off payments only
            ->whereNotNull('period_end')
            ->where('is_active', true)
            ->latest()
            ->get();

        return Inertia::render('payments/employee-index', [
            'employee' => $employee->load('business'),
            'payments' => $payments,
        ]);
    }

    /**
     * Show the form for editing the specified payment.
     */
    public function edit(Adjustment $payment): Response
    {
        // Ensure this is a one-off payment
        if ($payment->period_start === null || $payment->period_end === null) {
            abort(404, 'This is not a payment. Use Benefits to edit recurring adjustments.');
        }

        $businesses = Auth::user()->businesses()->get();
        $employee = $payment->employee;

        // Get employees for the business (for multi-select if company-wide)
        $employees = Employee::where('business_id', $payment->business_id)
            ->select(['id', 'name', 'email'])
            ->orderBy('name')
            ->get();

        return Inertia::render('payments/edit', [
            'payment' => $payment->load(['business', 'employee']),
            'businesses' => $businesses,
            'employee' => $employee,
            'employees' => $employees,
        ]);
    }

    /**
     * Update the specified payment.
     */
    public function update(Request $request, Adjustment $payment)
    {
        // Ensure this is a one-off payment
        if ($payment->period_start === null || $payment->period_end === null) {
            abort(404, 'This is not a payment.');
        }

        $validated = $request->validate([
            'business_id' => 'required|exists:businesses,id',
            'employee_id' => 'nullable|exists:employees,id',
            'name' => 'required|string|max:255',
            'type' => 'required|in:fixed,percentage',
            'amount' => 'required|numeric|min:0',
            'adjustment_type' => 'required|in:deduction,addition',
            'period_start' => 'required|date',
            'period_end' => 'required|date',
            'is_active' => 'boolean',
            'description' => 'nullable|string',
        ]);

        // Validate percentage amount
        if ($validated['type'] === 'percentage' && $validated['amount'] > 100) {
            return back()
                ->withErrors(['amount' => 'Percentage cannot exceed 100%.'])
                ->withInput();
        }

        // Ensure start <= end
        $periodStart = Carbon::parse($validated['period_start']);
        $periodEnd = Carbon::parse($validated['period_end']);

        if ($periodStart->gt($periodEnd)) {
            return back()
                ->withErrors(['period_start' => 'Start date must be before or equal to end date.'])
                ->withInput();
        }

        DB::transaction(function () use ($validated, $payment) {
            $payment->update($validated);
            $this->auditService->log('payment.updated', $payment, [
                'old' => $payment->getOriginal(),
                'new' => $payment->getChanges(),
            ]);
        });

        if ($validated['employee_id']) {
            return redirect()->route('employees.edit', $validated['employee_id'])
                ->with('success', 'Payment updated successfully.');
        }

        return redirect()->route('bonuses.index', ['business_id' => $validated['business_id']])
            ->with('success', 'Payment updated successfully.');
    }

    /**
     * Remove the specified payment.
     */
    public function destroy(Adjustment $payment)
    {
        // Ensure this is a one-off payment
        if ($payment->period_start === null || $payment->period_end === null) {
            abort(404, 'This is not a payment.');
        }

        $businessId = $payment->business_id;
        $employeeId = $payment->employee_id;

        $this->auditService->log('payment.deleted', $payment, $payment->getAttributes());

        $payment->delete();

        if ($employeeId) {
            return redirect()->route('employees.edit', $employeeId)
                ->with('success', 'Payment deleted successfully.');
        }

        return redirect()->route('bonuses.index', ['business_id' => $businessId])
            ->with('success', 'Payment deleted successfully.');
    }
}
