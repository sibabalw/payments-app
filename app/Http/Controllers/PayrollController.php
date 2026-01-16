<?php

namespace App\Http\Controllers;

use App\Mail\PayrollScheduleCancelledEmail;
use App\Mail\PayrollScheduleCreatedEmail;
use App\Models\Business;
use App\Models\Employee;
use App\Models\PayrollSchedule;
use App\Rules\BusinessDay;
use App\Services\AuditService;
use App\Services\CronExpressionService;
use App\Services\CronExpressionParser;
use App\Services\EmailService;
use App\Services\EscrowService;
use App\Services\SouthAfricanTaxService;
use Carbon\Carbon;
use Cron\CronExpression;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PayrollController extends Controller
{
    public function __construct(
        protected AuditService $auditService,
        protected EscrowService $escrowService,
        protected CronExpressionService $cronService,
        protected CronExpressionParser $cronParser,
        protected SouthAfricanTaxService $taxService
    ) {
    }

    /**
     * Display a listing of payroll schedules.
     */
    public function index(Request $request): Response
    {
        $businessId = $request->get('business_id') ?? Auth::user()->current_business_id ?? session('current_business_id');
        $status = $request->get('status');

        $query = PayrollSchedule::query()
            ->with(['business', 'employees']);

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
            'employee_ids' => 'required|array|min:1',
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
        if (!$business->canPerformActions()) {
            return back()
                ->withErrors(['business_id' => "Cannot create payroll schedule. Business is {$business->status}."])
                ->withInput();
        }

        // Generate cron expression from date/time or use provided frequency
        $frequency = $validated['frequency'] ?? null;
        
        if (isset($validated['scheduled_date']) && isset($validated['scheduled_time'])) {
            // New format: Generate cron from date/time
            $dateTime = Carbon::parse($validated['scheduled_date'] . ' ' . $validated['scheduled_time']);
            
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

        // Wrap essential database operations in a transaction
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
                $schedule->next_run_at = $cron->getNextRunDate(now());
                $schedule->save();
            } catch (\Exception $e) {
                // If calculation fails, set to null (will be handled by scheduler)
            }

            return $schedule;
        });

        // Attach employees outside transaction to avoid lock timeout
        $schedule->employees()->attach($validated['employee_ids']);

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

        // Parse cron expression to extract date/time for editing
        $parsed = $this->cronParser->parse($payrollSchedule->frequency);
        
        $schedule = $payrollSchedule->load(['business', 'employees']);
        if ($parsed) {
            $schedule->scheduled_date = $parsed['date'];
            $schedule->scheduled_time = $parsed['time'];
            $schedule->parsed_frequency = $parsed['frequency'];
        }

        // Calculate tax breakdowns for all employees in the schedule
        $employeeTaxBreakdowns = [];
        foreach ($schedule->employees as $employee) {
            $employeeTaxBreakdowns[$employee->id] = $this->taxService->calculateNetSalary($employee->gross_salary);
        }

        return Inertia::render('payroll/edit', [
            'schedule' => $schedule,
            'businesses' => $businesses,
            'employees' => $employees,
            'employeeTaxBreakdowns' => $employeeTaxBreakdowns,
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
            'employee_ids' => 'required|array|min:1',
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
        if (!$business->canPerformActions()) {
            return back()
                ->withErrors(['business_id' => "Cannot update payroll schedule. Business is {$business->status}."])
                ->withInput();
        }

        // Generate cron expression from date/time or use provided frequency
        $frequency = $validated['frequency'] ?? null;
        
        if (isset($validated['scheduled_date']) && isset($validated['scheduled_time'])) {
            // New format: Generate cron from date/time
            $dateTime = Carbon::parse($validated['scheduled_date'] . ' ' . $validated['scheduled_time']);
            
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
                    $payrollSchedule->next_run_at = $cron->getNextRunDate(now());
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
     * Resume a payroll schedule.
     */
    public function resume(PayrollSchedule $payrollSchedule)
    {
        // Recalculate next run time when resuming
        try {
            $cron = CronExpression::factory($payrollSchedule->frequency);
            $nextRun = $cron->getNextRunDate(now());
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

        $query = \App\Models\PayrollJob::query()
            ->with(['payrollSchedule.business', 'employee']);

        if ($status) {
            $query->where('status', $status);
        }

        if ($businessId) {
            $query->whereHas('payrollSchedule', function ($q) use ($businessId) {
                $q->where('business_id', $businessId);
            });
        } else {
            $userBusinessIds = Auth::user()->businesses()->pluck('businesses.id');
            $query->whereHas('payrollSchedule', function ($q) use ($userBusinessIds) {
                $q->whereIn('business_id', $userBusinessIds);
            });
        }

        $jobs = $query->latest()->paginate(20);

        return Inertia::render('payroll/jobs', [
            'jobs' => $jobs,
            'filters' => [
                'status' => $status,
                'business_id' => $businessId,
            ],
        ]);
    }
}
