<?php

namespace App\Http\Controllers;

use App\Mail\PayrollScheduleCancelledEmail;
use App\Models\Business;
use App\Models\Employee;
use App\Models\PayrollSchedule;
use App\Rules\BusinessDay;
use App\Services\AuditService;
use App\Services\CronExpressionParser;
use App\Services\CronExpressionService;
use App\Services\EmailService;
use App\Services\EscrowService;
use App\Services\SouthAfricaHolidayService;
use App\Services\SouthAfricanTaxService;
use Carbon\Carbon;
use Cron\CronExpression;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class PayrollController extends Controller
{
    public function __construct(
        protected AuditService $auditService,
        protected EscrowService $escrowService,
        protected CronExpressionService $cronService,
        protected CronExpressionParser $cronParser,
        protected SouthAfricanTaxService $taxService,
        protected SouthAfricaHolidayService $holidayService
    ) {}

    /**
     * Display a listing of payroll schedules.
     */
    public function index(Request $request): Response
    {
        $businessId = $request->get('business_id') ?? Auth::user()->current_business_id ?? session('current_business_id');
        $status = $request->get('status');

        $query = PayrollSchedule::query()
            ->select([
                'id',
                'business_id',
                'name',
                'frequency',
                'schedule_type',
                'status',
                'next_run_at',
                'created_at',
                'updated_at',
            ])
            ->with([
                'business:id,name',
                'employees:id,name,email',
            ]);

        if ($businessId) {
            $query->where('business_id', $businessId);
        } else {
            $userBusinessIds = Auth::user()->businesses()->pluck('businesses.id');
            $query->whereIn('business_id', $userBusinessIds);
        }

        if ($status) {
            $query->where('status', $status);
        }

        $schedules = $query->latest()->paginate(15);

        return Inertia::render('payroll/index', [
            'schedules' => $schedules,
            'filters' => [
                'status' => $status,
                'business_id' => $businessId,
            ],
        ]);
    }

    /**
     * Show the form for creating a new payroll schedule.
     */
    public function create(Request $request): Response
    {
        $businessId = $request->get('business_id') ?? Auth::user()->current_business_id ?? session('current_business_id');
        $businesses = Auth::user()->businesses()->get();

        $employees = [];
        $escrowBalance = null;
        if ($businessId) {
            $employees = Employee::where('business_id', $businessId)->get();
            $business = Business::find($businessId);
            if ($business) {
                $escrowBalance = $this->escrowService->getAvailableBalance($business);
            }
        }

        return Inertia::render('payroll/create', [
            'businesses' => $businesses,
            'employees' => $employees,
            'selectedBusinessId' => $businessId,
            'escrowBalance' => $escrowBalance,
        ]);
    }

    /**
     * Store a newly created payroll schedule.
     */
    public function store(Request $request)
    {
        // Accept both old format (frequency as cron) and new format (scheduled_date/scheduled_time)
        $rules = [
            'business_id' => 'required|exists:businesses,id',
            'name' => 'required|string|max:255',
            'schedule_type' => 'required|in:one_time,recurring',
            'employee_ids' => 'required|array',
            'employee_ids.*' => 'exists:employees,id',
        ];

        // If scheduled_date is provided, use new format; otherwise use old format for backward compatibility
        if ($request->has('scheduled_date')) {
            $rules['scheduled_date'] = ['required', 'date', new BusinessDay(app(\App\Services\SouthAfricaHolidayService::class))];
            $rules['scheduled_time'] = 'required|date_format:H:i';
            if ($request->input('schedule_type') === 'recurring') {
                $rules['frequency'] = 'required|in:daily,weekly,monthly';
            }
        } else {
            $rules['frequency'] = 'required|string';
        }

        $validated = $request->validate($rules);

        // Check if business is active
        $business = Business::findOrFail($validated['business_id']);
        if (! $business->canPerformActions()) {
            return back()
                ->withErrors(['business_id' => "Cannot create payroll schedule. Business is {$business->status}."])
                ->withInput();
        }

        // Generate cron expression from date/time or use provided frequency
        $frequency = $validated['frequency'] ?? null;

        if (isset($validated['scheduled_date']) && isset($validated['scheduled_time'])) {
            // New format: Generate cron from date/time
            $dateTime = Carbon::parse($validated['scheduled_date'].' '.$validated['scheduled_time']);

            if ($validated['schedule_type'] === 'one_time') {
                $frequency = $this->cronService->fromOneTime($dateTime);
            } else {
                $frequency = $this->cronService->fromRecurring($dateTime, $validated['frequency']);
            }
        }

        // Validate cron expression
        try {
            CronExpression::factory($frequency);
        } catch (\Exception $e) {
            return back()->withErrors(['frequency' => 'Invalid cron expression.'])->withInput();
        }

        // If employee_ids is empty, it means "all employees" - get all employees for the business
        if (empty($validated['employee_ids'])) {
            $validated['employee_ids'] = Employee::where('business_id', $validated['business_id'])
                ->pluck('id')
                ->toArray();
        }

        // Enforce rule: One employee = One recurring payroll schedule
        // Check BEFORE creating schedule to avoid inconsistent state
        if ($validated['schedule_type'] === 'recurring') {
            $employeesInOtherRecurringSchedules = \App\Models\Employee::whereIn('id', $validated['employee_ids'])
                ->whereHas('payrollSchedules', function ($query) {
                    $query->where('payroll_schedules.schedule_type', 'recurring')
                        ->where('payroll_schedules.status', 'active');
                })
                ->get();

            if ($employeesInOtherRecurringSchedules->isNotEmpty()) {
                $employeeNames = $employeesInOtherRecurringSchedules->pluck('name')->join(', ');

                return back()
                    ->withErrors(['employee_ids' => "The following employees are already in another recurring payroll schedule: {$employeeNames}. Each employee can only be in one recurring schedule."])
                    ->withInput();
            }
        }

        // Wrap all database operations in a transaction for atomicity
        $schedule = DB::transaction(function () use ($validated, $frequency) {
            $schedule = PayrollSchedule::create([
                'business_id' => $validated['business_id'],
                'name' => $validated['name'],
                'frequency' => $frequency,
                'schedule_type' => $validated['schedule_type'],
                'status' => 'active',
            ]);

            // Calculate next run time
            try {
                $cron = CronExpression::factory($frequency);
                // Use current time in app timezone for consistent calculation
                $nextRun = Carbon::instance($cron->getNextRunDate(now(config('app.timezone'))));
                // Skip weekends and holidays
                $nextRun = $this->adjustToBusinessDay($nextRun, $cron);
                $schedule->next_run_at = $nextRun;
                $schedule->save();
            } catch (\Exception $e) {
                // If calculation fails, set to null (will be handled by scheduler)
            }

            // Attach employees inside transaction to ensure atomicity
            // Employee attachment is fast (pivot table insert) and shouldn't cause lock issues
            $schedule->employees()->attach($validated['employee_ids']);

            return $schedule;
        });

        // Log audit trail outside transaction (non-critical)
        $this->auditService->log('payroll_schedule.created', $schedule, $schedule->getAttributes());

        // Send payroll schedule created email (non-critical, happens after transaction)
        $user = $business->owner;
        $emailService = app(EmailService::class);
        $emailService->send($user, new \App\Mail\PayrollScheduleCreatedEmail($user, $schedule), 'payroll_schedule_created');

        return redirect()->route('payroll.index')
            ->with('success', 'Payroll schedule created successfully.');
    }

    /**
     * Show the form for editing the specified payroll schedule.
     */
    public function edit(PayrollSchedule $payrollSchedule): Response
    {
        $businesses = Auth::user()->businesses()->get();
        $employees = Employee::where('business_id', $payrollSchedule->business_id)->get();

        $schedule = $payrollSchedule->load([
            'business',
            'employees.timeEntries' => function ($query) {
                $query->whereNotNull('sign_out_time');
            },
        ]);

        // Use next_run_at to populate date/time fields, fallback to parsing cron if not available
        if ($schedule->next_run_at) {
            $nextRun = \Carbon\Carbon::parse($schedule->next_run_at);
            $schedule->scheduled_date = $nextRun->format('Y-m-d');
            $schedule->scheduled_time = $nextRun->format('H:i');
        } else {
            // Fallback: Parse cron expression if next_run_at is not set
            $parsed = $this->cronParser->parse($payrollSchedule->frequency);
            if ($parsed) {
                $schedule->scheduled_date = $parsed['date'];
                $schedule->scheduled_time = $parsed['time'];
            }
        }

        // Parse frequency for display
        $parsed = $this->cronParser->parse($payrollSchedule->frequency);
        if ($parsed) {
            $schedule->parsed_frequency = $parsed['frequency'];
        }

        // Tax breakdowns are now loaded on-demand via getTaxBreakdowns endpoint
        // This prevents blocking the page load when there are many employees

        return Inertia::render('payroll/edit', [
            'schedule' => $schedule,
            'businesses' => $businesses,
            'employees' => $employees,
        ]);
    }

    /**
     * Update the specified payroll schedule.
     */
    public function update(Request $request, PayrollSchedule $payrollSchedule)
    {
        // Accept both old format (frequency as cron) and new format (scheduled_date/scheduled_time)
        $rules = [
            'business_id' => 'required|exists:businesses,id',
            'name' => 'required|string|max:255',
            'schedule_type' => 'required|in:one_time,recurring',
            'employee_ids' => 'required|array',
            'employee_ids.*' => 'exists:employees,id',
        ];

        // If scheduled_date is provided, use new format; otherwise use old format for backward compatibility
        if ($request->has('scheduled_date')) {
            $rules['scheduled_date'] = ['required', 'date', new BusinessDay(app(\App\Services\SouthAfricaHolidayService::class))];
            $rules['scheduled_time'] = 'required|date_format:H:i';
            if ($request->input('schedule_type') === 'recurring') {
                $rules['frequency'] = 'required|in:daily,weekly,monthly';
            }
        } else {
            $rules['frequency'] = 'required|string';
        }

        $validated = $request->validate($rules);

        // Check if business is active
        $business = Business::findOrFail($validated['business_id']);
        if (! $business->canPerformActions()) {
            return back()
                ->withErrors(['business_id' => "Cannot update payroll schedule. Business is {$business->status}."])
                ->withInput();
        }

        // Generate cron expression from date/time or use provided frequency
        $frequency = $validated['frequency'] ?? null;

        if (isset($validated['scheduled_date']) && isset($validated['scheduled_time'])) {
            // New format: Generate cron from date/time
            $dateTime = Carbon::parse($validated['scheduled_date'].' '.$validated['scheduled_time']);

            if ($validated['schedule_type'] === 'one_time') {
                $frequency = $this->cronService->fromOneTime($dateTime);
            } else {
                $frequency = $this->cronService->fromRecurring($dateTime, $validated['frequency']);
            }
        }

        // Validate cron expression
        try {
            CronExpression::factory($frequency);
        } catch (\Exception $e) {
            return back()->withErrors(['frequency' => 'Invalid cron expression.'])->withInput();
        }

        // If employee_ids is empty, it means "all employees" - get all employees for the business
        if (empty($validated['employee_ids'])) {
            $validated['employee_ids'] = Employee::where('business_id', $validated['business_id'])
                ->pluck('id')
                ->toArray();
        }

        // Enforce rule: One employee = One recurring payroll schedule
        // Check if any employee is already in another recurring schedule (before transaction)
        if ($validated['schedule_type'] === 'recurring') {
            $employeesInOtherRecurringSchedules = \App\Models\Employee::whereIn('id', $validated['employee_ids'])
                ->whereHas('payrollSchedules', function ($query) use ($payrollSchedule) {
                    $query->where('payroll_schedules.schedule_type', 'recurring')
                        ->where('payroll_schedules.status', 'active')
                        ->where('payroll_schedules.id', '!=', $payrollSchedule->id);
                })
                ->get();

            if ($employeesInOtherRecurringSchedules->isNotEmpty()) {
                $employeeNames = $employeesInOtherRecurringSchedules->pluck('name')->join(', ');

                return back()
                    ->withErrors(['employee_ids' => "The following employees are already in another recurring payroll schedule: {$employeeNames}. Each employee can only be in one recurring schedule."])
                    ->withInput();
            }
        }

        // Wrap all database operations in a transaction
        DB::transaction(function () use ($validated, $frequency, $payrollSchedule) {
            $payrollSchedule->update([
                'business_id' => $validated['business_id'],
                'name' => $validated['name'],
                'frequency' => $frequency,
                'schedule_type' => $validated['schedule_type'],
            ]);

            // Recalculate next run time if frequency changed
            if ($payrollSchedule->wasChanged('frequency')) {
                try {
                    $cron = CronExpression::factory($frequency);
                    $nextRun = Carbon::instance($cron->getNextRunDate(now(config('app.timezone'))));
                    // Skip weekends and holidays
                    $nextRun = $this->adjustToBusinessDay($nextRun, $cron);
                    $payrollSchedule->next_run_at = $nextRun;
                    $payrollSchedule->save();
                } catch (\Exception $e) {
                    // If calculation fails, set to null
                }
            }

            // Sync employees
            $payrollSchedule->employees()->sync($validated['employee_ids']);

            // Log audit trail
            $this->auditService->log('payroll_schedule.updated', $payrollSchedule, [
                'old' => $payrollSchedule->getOriginal(),
                'new' => $payrollSchedule->getChanges(),
            ]);
        });

        return redirect()->route('payroll.index')
            ->with('success', 'Payroll schedule updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(PayrollSchedule $payrollSchedule)
    {
        $business = $payrollSchedule->business;
        $user = $business->owner;

        $this->auditService->log('payroll_schedule.deleted', $payrollSchedule, $payrollSchedule->getAttributes());

        // Send payroll schedule cancelled email before deleting
        $emailService = app(EmailService::class);
        $emailService->send($user, new PayrollScheduleCancelledEmail($user, $payrollSchedule), 'payroll_schedule_cancelled');

        $payrollSchedule->delete();

        return redirect()->route('payroll.index')
            ->with('success', 'Payroll schedule deleted successfully.');
    }

    /**
     * Pause a payroll schedule.
     */
    public function pause(PayrollSchedule $payrollSchedule)
    {
        $payrollSchedule->update(['status' => 'paused']);

        $this->auditService->log('payroll_schedule.paused', $payrollSchedule, [
            'status' => 'paused',
        ]);

        return redirect()->back()
            ->with('success', 'Payroll schedule paused.');
    }

    /**
     * Get tax breakdowns for employees in a payroll schedule (on-demand)
     */
    public function getTaxBreakdowns(PayrollSchedule $payrollSchedule): \Illuminate\Http\JsonResponse
    {
        $schedule = $payrollSchedule->load([
            'employees.adjustments' => function ($query) {
                $query->where('is_active', true);
            },
            'employees.timeEntries' => function ($query) {
                $query->whereNotNull('sign_out_time');
            },
        ]);

        // Use current month period for preview
        $periodStart = \Carbon\Carbon::parse(now()->startOfMonth());
        $periodEnd = \Carbon\Carbon::parse(now()->endOfMonth());
        $adjustmentService = app(\App\Services\AdjustmentService::class);

        // Calculate tax breakdowns for all employees in the schedule
        $employeeTaxBreakdowns = [];
        foreach ($schedule->employees as $employee) {
            // Get valid adjustments for the period
            $adjustments = $adjustmentService->getValidAdjustments($employee, $periodStart, $periodEnd);

            // Calculate net salary after statutory deductions
            $breakdown = $this->taxService->calculateNetSalary($employee->gross_salary, [
                'uif_exempt' => $employee->isUIFExempt(),
            ]);

            // Apply adjustments
            $adjustmentResult = $adjustmentService->applyAdjustments(
                $breakdown['net'],
                $adjustments,
                $employee->gross_salary
            );

            // Merge results
            $breakdown['adjustments'] = $adjustmentResult['adjustments_breakdown'];
            $breakdown['total_adjustments'] = $adjustmentResult['total_adjustments'];
            $breakdown['final_net_salary'] = $adjustmentResult['final_net_salary'];

            $employeeTaxBreakdowns[$employee->id] = $breakdown;
        }

        return response()->json($employeeTaxBreakdowns);
    }

    /**
     * Resume a payroll schedule.
     */
    public function resume(PayrollSchedule $payrollSchedule)
    {
        // Recalculate next run time when resuming
        try {
            $cron = CronExpression::factory($payrollSchedule->frequency);
            $nextRun = Carbon::instance($cron->getNextRunDate(now(config('app.timezone'))));
            // Skip weekends and holidays
            $nextRun = $this->adjustToBusinessDay($nextRun, $cron);
            $payrollSchedule->update([
                'status' => 'active',
                'next_run_at' => $nextRun,
            ]);
        } catch (\Exception $e) {
            $payrollSchedule->update(['status' => 'active']);
        }

        $this->auditService->log('payroll_schedule.resumed', $payrollSchedule, [
            'status' => 'active',
        ]);

        return redirect()->back()
            ->with('success', 'Payroll schedule resumed.');
    }

    /**
     * Cancel a payroll schedule.
     */
    public function cancel(PayrollSchedule $payrollSchedule)
    {
        $payrollSchedule->update(['status' => 'cancelled']);

        $this->auditService->log('payroll_schedule.cancelled', $payrollSchedule, [
            'status' => 'cancelled',
        ]);

        return redirect()->back()
            ->with('success', 'Payroll schedule cancelled.');
    }

    /**
     * Display payroll jobs for payroll schedules.
     */
    public function jobs(Request $request): Response
    {
        $businessId = $request->get('business_id') ?? Auth::user()->current_business_id ?? session('current_business_id');
        $status = $request->get('status');

        // Use JOIN instead of whereHas for better performance
        $query = \App\Models\PayrollJob::query()
            ->select(['payroll_jobs.*'])
            ->join('payroll_schedules', 'payroll_jobs.payroll_schedule_id', '=', 'payroll_schedules.id')
            ->with(['payrollSchedule.business', 'employee']);

        if ($status) {
            $query->where('payroll_jobs.status', $status);
        }

        if ($businessId) {
            $query->where('payroll_schedules.business_id', $businessId);
        } else {
            $userBusinessIds = Auth::user()->businesses()->pluck('businesses.id')->toArray();
            $query->whereIn('payroll_schedules.business_id', $userBusinessIds);
        }

        $jobs = $query->orderByDesc('payroll_jobs.created_at')->paginate(20);

        return Inertia::render('payroll/jobs', [
            'jobs' => $jobs,
            'filters' => [
                'status' => $status,
                'business_id' => $businessId,
            ],
        ]);
    }

    /**
     * Adjust a date to the next business day if it falls on a weekend or holiday.
     */
    private function adjustToBusinessDay(Carbon $date, CronExpression $cron): Carbon
    {
        if ($this->holidayService->isBusinessDay($date)) {
            return $date;
        }

        $originalDate = $date->format('Y-m-d');
        $originalTime = $date->format('H:i');
        $adjustedDate = $this->holidayService->getNextBusinessDay($date);
        $adjustedDate->setTime((int) $date->format('H'), (int) $date->format('i'));

        Log::info('Payroll schedule next run adjusted to skip weekend/holiday', [
            'original_date' => $originalDate,
            'adjusted_date' => $adjustedDate->format('Y-m-d'),
            'time' => $originalTime,
        ]);

        return $adjustedDate;
    }
}
