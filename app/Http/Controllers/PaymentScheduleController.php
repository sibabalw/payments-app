<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\PaymentSchedule;
use App\Models\Receiver;
use App\Rules\BusinessDay;
use App\Services\AuditService;
use App\Services\CronExpressionService;
use App\Services\CronExpressionParser;
use App\Services\EscrowService;
use Carbon\Carbon;
use Cron\CronExpression;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class PaymentScheduleController extends Controller
{
    public function __construct(
        protected AuditService $auditService,
        protected EscrowService $escrowService,
        protected CronExpressionService $cronService,
        protected CronExpressionParser $cronParser
    ) {
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        $businessId = $request->get('business_id') ?? session('current_business_id');
        $type = $request->get('type'); // 'generic' or 'payroll'
        $status = $request->get('status'); // 'active', 'paused', 'cancelled'

        $query = PaymentSchedule::query()->with(['business', 'receivers']);

        if ($businessId) {
            $query->where('business_id', $businessId);
        } else {
            $userBusinessIds = Auth::user()->businesses()->pluck('businesses.id');
            $query->whereIn('business_id', $userBusinessIds);
        }

        if ($type) {
            $query->ofType($type);
        }

        if ($status) {
            $query->where('status', $status);
        }

        $schedules = $query->latest()->paginate(15);

        return Inertia::render('payments/index', [
            'schedules' => $schedules,
            'filters' => [
                'type' => $type,
                'status' => $status,
                'business_id' => $businessId,
            ],
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request): Response
    {
        $businessId = $request->get('business_id') ?? session('current_business_id');
        $type = $request->get('type', 'generic');
        $businesses = Auth::user()->businesses()->get();
        
        $receivers = [];
        $escrowBalance = null;
        if ($businessId) {
            $receivers = Receiver::where('business_id', $businessId)->get();
            $business = Business::find($businessId);
            if ($business) {
                $escrowBalance = $this->escrowService->getAvailableBalance($business);
            }
        }

        return Inertia::render('payments/create', [
            'businesses' => $businesses,
            'receivers' => $receivers,
            'selectedBusinessId' => $businessId,
            'type' => $type,
            'escrowBalance' => $escrowBalance,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // Accept both old format (frequency as cron) and new format (scheduled_date/scheduled_time)
        $rules = [
            'business_id' => 'required|exists:businesses,id',
            'type' => 'required|in:generic,payroll',
            'name' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01',
            'schedule_type' => 'required|in:one_time,recurring',
            'receiver_ids' => 'required|array|min:1',
            'receiver_ids.*' => 'exists:receivers,id',
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
                ->withErrors(['business_id' => "Cannot create payment schedule. Business is {$business->status}."])
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

        $schedule = PaymentSchedule::create([
            'business_id' => $validated['business_id'],
            'type' => $validated['type'],
            'name' => $validated['name'],
            'frequency' => $frequency,
            'amount' => $validated['amount'],
            'currency' => 'ZAR',
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

        // Attach receivers
        $schedule->receivers()->attach($validated['receiver_ids']);

        $this->auditService->log('payment_schedule.created', $schedule, $schedule->getAttributes());

        $route = $validated['type'] === 'payroll' ? 'payroll.index' : 'payments.index';

        return redirect()->route($route)
            ->with('success', 'Payment schedule created successfully.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(PaymentSchedule $paymentSchedule): Response
    {
        $businesses = Auth::user()->businesses()->get();
        $receivers = Receiver::where('business_id', $paymentSchedule->business_id)->get();

        // Parse cron expression to extract date/time for editing
        $parsed = $this->cronParser->parse($paymentSchedule->frequency);
        
        $schedule = $paymentSchedule->load(['business', 'receivers']);
        if ($parsed) {
            $schedule->scheduled_date = $parsed['date'];
            $schedule->scheduled_time = $parsed['time'];
            $schedule->parsed_frequency = $parsed['frequency'];
        }

        return Inertia::render('payments/edit', [
            'schedule' => $schedule,
            'businesses' => $businesses,
            'receivers' => $receivers,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, PaymentSchedule $paymentSchedule)
    {
        // Accept both old format (frequency as cron) and new format (scheduled_date/scheduled_time)
        $rules = [
            'business_id' => 'required|exists:businesses,id',
            'name' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'required|string|size:3',
            'schedule_type' => 'required|in:one_time,recurring',
            'receiver_ids' => 'required|array|min:1',
            'receiver_ids.*' => 'exists:receivers,id',
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
                ->withErrors(['business_id' => "Cannot update payment schedule. Business is {$business->status}."])
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

        $paymentSchedule->update([
            'business_id' => $validated['business_id'],
            'name' => $validated['name'],
            'frequency' => $frequency,
            'amount' => $validated['amount'],
            'currency' => 'ZAR',
            'schedule_type' => $validated['schedule_type'],
        ]);

        // Recalculate next run time if frequency changed
        if ($paymentSchedule->wasChanged('frequency')) {
            try {
                $cron = CronExpression::factory($frequency);
                $paymentSchedule->next_run_at = $cron->getNextRunDate(now());
                $paymentSchedule->save();
            } catch (\Exception $e) {
                // If calculation fails, set to null
            }
        }

        // Sync receivers
        $paymentSchedule->receivers()->sync($validated['receiver_ids']);

        $this->auditService->log('payment_schedule.updated', $paymentSchedule, [
            'old' => $paymentSchedule->getOriginal(),
            'new' => $paymentSchedule->getChanges(),
        ]);

        $route = $paymentSchedule->type === 'payroll' ? 'payroll.index' : 'payments.index';

        return redirect()->route($route)
            ->with('success', 'Payment schedule updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(PaymentSchedule $paymentSchedule)
    {
        $this->auditService->log('payment_schedule.deleted', $paymentSchedule, $paymentSchedule->getAttributes());

        $paymentSchedule->delete();

        $route = $paymentSchedule->type === 'payroll' ? 'payroll.index' : 'payments.index';

        return redirect()->route($route)
            ->with('success', 'Payment schedule deleted successfully.');
    }

    /**
     * Pause a payment schedule.
     */
    public function pause(PaymentSchedule $paymentSchedule)
    {
        $paymentSchedule->update(['status' => 'paused']);

        $this->auditService->log('payment_schedule.paused', $paymentSchedule, [
            'status' => 'paused',
        ]);

        return redirect()->back()
            ->with('success', 'Payment schedule paused.');
    }

    /**
     * Resume a payment schedule.
     */
    public function resume(PaymentSchedule $paymentSchedule)
    {
        // Recalculate next run time when resuming
        try {
            $cron = CronExpression::factory($paymentSchedule->frequency);
            $nextRun = $cron->getNextRunDate(now());
            $paymentSchedule->update([
                'status' => 'active',
                'next_run_at' => $nextRun,
            ]);
        } catch (\Exception $e) {
            $paymentSchedule->update(['status' => 'active']);
        }

        $this->auditService->log('payment_schedule.resumed', $paymentSchedule, [
            'status' => 'active',
        ]);

        return redirect()->back()
            ->with('success', 'Payment schedule resumed.');
    }

    /**
     * Cancel a payment schedule.
     */
    public function cancel(PaymentSchedule $paymentSchedule)
    {
        $paymentSchedule->update(['status' => 'cancelled']);

        $this->auditService->log('payment_schedule.cancelled', $paymentSchedule, [
            'status' => 'cancelled',
        ]);

        return redirect()->back()
            ->with('success', 'Payment schedule cancelled.');
    }
}
