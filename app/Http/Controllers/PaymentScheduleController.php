<?php

namespace App\Http\Controllers;

use App\Mail\PaymentScheduleCancelledEmail;
use App\Mail\PaymentScheduleCreatedEmail;
use App\Models\Business;
use App\Models\PaymentSchedule;
use App\Models\Recipient;
use App\Rules\BusinessDay;
use App\Services\AuditService;
use App\Services\CronExpressionParser;
use App\Services\CronExpressionService;
use App\Services\EmailService;
use App\Services\EscrowService;
use App\Services\SouthAfricaHolidayService;
use Carbon\Carbon;
use Cron\CronExpression;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class PaymentScheduleController extends Controller
{
    public function __construct(
        protected AuditService $auditService,
        protected EscrowService $escrowService,
        protected CronExpressionService $cronService,
        protected CronExpressionParser $cronParser,
        protected SouthAfricaHolidayService $holidayService
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        $businessId = $request->get('business_id') ?? Auth::user()->current_business_id ?? session('current_business_id');
        $type = $request->get('type'); // 'generic' or 'payroll'
        $status = $request->get('status'); // 'active', 'paused', 'cancelled'

        $query = PaymentSchedule::query()
            ->select([
                'id',
                'business_id',
                'type',
                'name',
                'frequency',
                'amount',
                'currency',
                'schedule_type',
                'status',
                'next_run_at',
                'created_at',
                'updated_at',
            ])
            ->with([
                'business:id,name',
                'recipients:id,name,email',
            ]);

        if ($businessId) {
            $query->where('business_id', $businessId);
        } else {
            $userBusinessIds = Auth::user()->businesses()->pluck('businesses.id');
            $query->whereIn('business_id', $userBusinessIds);
        }

        // Only show generic payment schedules (not payroll)
        $query->ofType('generic');

        if ($type && $type !== 'generic') {
            // If type is specified and not generic, return empty (payroll uses separate controller)
            $query->whereRaw('1 = 0');
        }

        if ($status) {
            $query->where('status', $status);
        }

        $schedules = $query->latest()->paginate(15);

        return Inertia::render('payments/schedules', [
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
        $businessId = $request->get('business_id') ?? Auth::user()->current_business_id ?? session('current_business_id');
        $type = $request->get('type', 'generic');
        $businesses = Auth::user()->businesses()->get();

        $recipients = [];
        $escrowBalance = null;
        if ($businessId) {
            $recipients = Recipient::where('business_id', $businessId)->get();
            $business = Business::find($businessId);
            if ($business) {
                $escrowBalance = $this->escrowService->getAvailableBalance($business);
            }
        }

        return Inertia::render('payments/schedule-create', [
            'businesses' => $businesses,
            'recipients' => $recipients,
            'selectedBusinessId' => $businessId,
            'type' => 'generic',
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
            'name' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01',
            'schedule_type' => 'required|in:one_time,recurring',
            'recipient_ids' => 'required|array|min:1',
            'recipient_ids.*' => 'exists:recipients,id',
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
                ->withErrors(['business_id' => "Cannot create payment schedule. Business is {$business->status}."])
                ->withInput();
        }

        // Check escrow balance before creating schedule
        // Note: This is a user-facing validation check. The actual job creation is protected
        // by database triggers and the ProcessScheduledPayments command which locks the business row.
        $escrowBalance = $this->escrowService->getAvailableBalance($business);
        $recipientCount = count($validated['recipient_ids']);
        $totalAmountRequired = $validated['amount'] * $recipientCount;

        // Explicit check: escrow balance must be greater than zero
        if ($escrowBalance <= 0) {
            return back()
                ->withErrors(['amount' => 'Cannot create payment schedule. Escrow balance is zero or negative. Please deposit funds first.'])
                ->withInput();
        }

        // Calculate total from existing active schedules
        $existingSchedulesTotal = PaymentSchedule::where('business_id', $validated['business_id'])
            ->where('status', 'active')
            ->with('recipients')
            ->get()
            ->sum(function ($schedule) {
                return $schedule->amount * max($schedule->recipients->count(), 1);
            });

        $totalScheduledAmount = $existingSchedulesTotal + $totalAmountRequired;

        // Explicit check: available balance must be sufficient for total amount required
        if ($escrowBalance < $totalAmountRequired) {
            return back()
                ->withErrors(['amount' => 'Insufficient escrow balance. Available: '.number_format($escrowBalance, 2).' ZAR, Required: '.number_format($totalAmountRequired, 2).' ZAR.'])
                ->withInput();
        }

        // Explicit check: total scheduled amount (existing + new) should not exceed available balance
        // This prevents creating schedules that would exceed balance when combined with existing schedules
        if ($escrowBalance < $totalScheduledAmount) {
            return back()
                ->withErrors(['amount' => 'Creating this schedule would exceed available escrow balance. Available: '.number_format($escrowBalance, 2).' ZAR, Total scheduled (including existing): '.number_format($totalScheduledAmount, 2).' ZAR.'])
                ->withInput();
        }

        // Generate cron expression from date/time or use provided frequency
        $frequency = $validated['frequency'] ?? null;

        if (isset($validated['scheduled_date']) && isset($validated['scheduled_time'])) {
            // New format: Generate cron from date/time
            // Parse in app timezone to ensure consistency
            $dateTime = Carbon::parse($validated['scheduled_date'].' '.$validated['scheduled_time'], config('app.timezone'));

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
        $schedule = DB::transaction(function () use ($validated, $frequency) {
            $schedule = PaymentSchedule::create([
                'business_id' => $validated['business_id'],
                'type' => 'generic',
                'name' => $validated['name'],
                'frequency' => $frequency,
                'amount' => $validated['amount'],
                'currency' => 'ZAR',
                'schedule_type' => $validated['schedule_type'],
                'status' => 'active',
            ]);

            // Calculate next run time (required for correctness — no fallback)
            try {
                $nextRun = $this->cronService->getNextRunDate($frequency, now(config('app.timezone')));
                // Skip weekends and holidays
                $nextRun = $this->adjustToBusinessDay($nextRun);
                $schedule->next_run_at = $nextRun;
                $schedule->save();
            } catch (\Throwable $e) {
                Log::error('Unable to compute next run date for payment schedule', [
                    'schedule_id' => $schedule->id,
                    'frequency' => $frequency,
                    'error' => $e->getMessage(),
                ]);
                throw new \RuntimeException(
                    'Unable to compute next run date for schedule: '.$e->getMessage().'. Fix the schedule or contact support.',
                    0,
                    $e
                );
            }

            return $schedule;
        });

        // CRITICAL: Re-validate escrow balance before attaching recipients
        // This provides application-level defense even if database triggers are bypassed
        $business = Business::findOrFail($validated['business_id']);
        $escrowBalance = $this->escrowService->getAvailableBalance($business);
        $recipientCount = count($validated['recipient_ids']);
        $totalAmountRequired = $schedule->amount * $recipientCount;

        // Explicit check: escrow balance must be greater than zero
        if ($escrowBalance <= 0) {
            // Delete the schedule since we can't attach recipients
            $schedule->delete();

            return back()
                ->withErrors(['amount' => 'Cannot attach recipients. Escrow balance is zero or negative. Please deposit funds first.'])
                ->withInput();
        }

        // Explicit check: available balance must be sufficient for total amount required
        if ($escrowBalance < $totalAmountRequired) {
            // Delete the schedule since we can't attach recipients
            $schedule->delete();

            return back()
                ->withErrors(['amount' => 'Insufficient escrow balance to attach recipients. Available: '.number_format($escrowBalance, 2).' ZAR, Required: '.number_format($totalAmountRequired, 2).' ZAR.'])
                ->withInput();
        }

        // Attach recipients outside transaction to avoid lock timeout
        $schedule->recipients()->attach($validated['recipient_ids']);

        // Log audit trail outside transaction (non-critical)
        $this->auditService->log('payment_schedule.created', $schedule, $schedule->getAttributes());

        // Send payment schedule created email (non-critical, happens after transaction)
        $user = $business->owner;
        $emailService = app(EmailService::class);
        $emailService->send($user, new PaymentScheduleCreatedEmail($user, $schedule), 'payment_schedule_created');

        $route = 'payments.index';

        return redirect()->route($route)
            ->with('success', 'Payment schedule created successfully.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(PaymentSchedule $paymentSchedule): Response
    {
        // Only allow editing generic payment schedules
        if ($paymentSchedule->type !== 'generic') {
            abort(404);
        }

        $businesses = Auth::user()->businesses()->get();
        $recipients = Recipient::where('business_id', $paymentSchedule->business_id)->get();

        $schedule = $paymentSchedule->load(['business', 'recipients']);

        // Use next_run_at to populate date/time fields; do not guess from cron when null
        if ($schedule->next_run_at) {
            $nextRun = \Carbon\Carbon::parse($schedule->next_run_at);
            $schedule->scheduled_date = $nextRun->format('Y-m-d');
            $schedule->scheduled_time = $nextRun->format('H:i');
            $schedule->next_run_at_missing = false;
        } else {
            $schedule->scheduled_date = null;
            $schedule->scheduled_time = null;
            $schedule->next_run_at_missing = true;
        }

        // Parse frequency for display
        $parsed = $this->cronParser->parse($paymentSchedule->frequency);
        if ($parsed) {
            $schedule->parsed_frequency = $parsed['frequency'];
        }

        return Inertia::render('payments/schedule-edit', [
            'schedule' => $schedule,
            'businesses' => $businesses,
            'recipients' => $recipients,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, PaymentSchedule $paymentSchedule)
    {
        // Only allow updating generic payment schedules
        if ($paymentSchedule->type !== 'generic') {
            abort(404);
        }

        // Accept both old format (frequency as cron) and new format (scheduled_date/scheduled_time)
        $rules = [
            'business_id' => 'required|exists:businesses,id',
            'name' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'required|string|size:3',
            'schedule_type' => 'required|in:one_time,recurring',
            'recipient_ids' => 'required|array|min:1',
            'recipient_ids.*' => 'exists:recipients,id',
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
                ->withErrors(['business_id' => "Cannot update payment schedule. Business is {$business->status}."])
                ->withInput();
        }

        // Check escrow balance before updating schedule
        $escrowBalance = $this->escrowService->getAvailableBalance($business);
        $recipientCount = count($validated['recipient_ids']);
        $totalAmountRequired = $validated['amount'] * $recipientCount;

        // Explicit check: escrow balance must be greater than zero
        if ($escrowBalance <= 0) {
            return back()
                ->withErrors(['amount' => 'Cannot update payment schedule. Escrow balance is zero or negative. Please deposit funds first.'])
                ->withInput();
        }

        // Calculate total from existing active schedules (excluding this one)
        $existingSchedulesTotal = PaymentSchedule::where('business_id', $validated['business_id'])
            ->where('status', 'active')
            ->where('id', '!=', $paymentSchedule->id)
            ->with('recipients')
            ->get()
            ->sum(function ($schedule) {
                return $schedule->amount * max($schedule->recipients->count(), 1);
            });

        $totalScheduledAmount = $existingSchedulesTotal + $totalAmountRequired;

        // Explicit check: available balance must be sufficient for total amount required
        if ($escrowBalance < $totalAmountRequired) {
            return back()
                ->withErrors(['amount' => 'Insufficient escrow balance. Available: '.number_format($escrowBalance, 2).' ZAR, Required: '.number_format($totalAmountRequired, 2).' ZAR.'])
                ->withInput();
        }

        // Explicit check: total scheduled amount (existing + updated) should not exceed available balance
        if ($escrowBalance < $totalScheduledAmount) {
            return back()
                ->withErrors(['amount' => 'Updating this schedule would exceed available escrow balance. Available: '.number_format($escrowBalance, 2).' ZAR, Total scheduled (including existing): '.number_format($totalScheduledAmount, 2).' ZAR.'])
                ->withInput();
        }

        // Generate cron expression from date/time or use provided frequency
        $frequency = $validated['frequency'] ?? null;

        if (isset($validated['scheduled_date']) && isset($validated['scheduled_time'])) {
            // New format: Generate cron from date/time
            // Parse in app timezone to ensure consistency
            $dateTime = Carbon::parse($validated['scheduled_date'].' '.$validated['scheduled_time'], config('app.timezone'));

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
        DB::transaction(function () use ($validated, $frequency, $paymentSchedule) {
            $paymentSchedule->update([
                'business_id' => $validated['business_id'],
                'name' => $validated['name'],
                'frequency' => $frequency,
                'amount' => $validated['amount'],
                'currency' => 'ZAR',
                'schedule_type' => $validated['schedule_type'],
            ]);

            // Recalculate next run time if frequency changed (required for correctness — no fallback)
            if ($paymentSchedule->wasChanged('frequency')) {
                try {
                    $nextRun = $this->cronService->getNextRunDate($frequency, now(config('app.timezone')));
                    // Skip weekends and holidays
                    $nextRun = $this->adjustToBusinessDay($nextRun);
                    $paymentSchedule->next_run_at = $nextRun;
                    $paymentSchedule->save();
                } catch (\Throwable $e) {
                    Log::error('Unable to compute next run date for payment schedule update', [
                        'schedule_id' => $paymentSchedule->id,
                        'frequency' => $frequency,
                        'error' => $e->getMessage(),
                    ]);
                    throw new \RuntimeException(
                        'Unable to compute next run date for schedule: '.$e->getMessage().'. Fix the schedule or contact support.',
                        0,
                        $e
                    );
                }
            }

            // CRITICAL: Re-validate escrow balance before syncing recipients
            // This provides application-level defense even if database triggers are bypassed
            $business = Business::findOrFail($validated['business_id']);
            $escrowBalance = $this->escrowService->getAvailableBalance($business);
            $recipientCount = count($validated['recipient_ids']);
            $totalAmountRequired = $paymentSchedule->amount * $recipientCount;

            // Explicit check: escrow balance must be greater than zero
            if ($escrowBalance <= 0) {
                throw new \RuntimeException('Cannot sync recipients. Escrow balance is zero or negative. Please deposit funds first.');
            }

            // Explicit check: available balance must be sufficient for total amount required
            if ($escrowBalance < $totalAmountRequired) {
                throw new \RuntimeException('Insufficient escrow balance to sync recipients. Available: '.number_format($escrowBalance, 2).' ZAR, Required: '.number_format($totalAmountRequired, 2).' ZAR.');
            }

            // Sync recipients
            $paymentSchedule->recipients()->sync($validated['recipient_ids']);

            // Log audit trail
            $this->auditService->log('payment_schedule.updated', $paymentSchedule, [
                'old' => $paymentSchedule->getOriginal(),
                'new' => $paymentSchedule->getChanges(),
            ]);
        });

        $route = 'payments.index';

        return redirect()->route($route)
            ->with('success', 'Payment schedule updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     * Permanently deletes the schedule but keeps full audit trail.
     */
    public function destroy(PaymentSchedule $paymentSchedule)
    {
        // Only allow deleting generic payment schedules
        if ($paymentSchedule->type !== 'generic') {
            abort(404);
        }

        // Load all related data before deletion for audit logging
        $paymentSchedule->load([
            'business:id,name,user_id',
            'business.owner:id,name,email',
            'recipients:id,name,email',
        ]);

        $business = $paymentSchedule->business;
        $user = $business->owner;

        // Capture comprehensive data for audit log before deletion
        $auditData = [
            'schedule' => [
                'id' => $paymentSchedule->id,
                'name' => $paymentSchedule->name,
                'business_id' => $paymentSchedule->business_id,
                'business_name' => $business->name,
                'type' => $paymentSchedule->type,
                'frequency' => $paymentSchedule->frequency,
                'amount' => $paymentSchedule->amount,
                'currency' => $paymentSchedule->currency,
                'schedule_type' => $paymentSchedule->schedule_type,
                'status' => $paymentSchedule->status,
                'next_run_at' => $paymentSchedule->next_run_at?->toIso8601String(),
                'last_run_at' => $paymentSchedule->last_run_at?->toIso8601String(),
                'created_at' => $paymentSchedule->created_at?->toIso8601String(),
                'updated_at' => $paymentSchedule->updated_at?->toIso8601String(),
            ],
            'recipients' => $paymentSchedule->recipients->map(function ($recipient) {
                return [
                    'id' => $recipient->id,
                    'name' => $recipient->name,
                    'email' => $recipient->email,
                ];
            })->toArray(),
            'deleted_by' => [
                'user_id' => Auth::id(),
                'user_email' => Auth::user()?->email,
            ],
            'deleted_at' => now()->toIso8601String(),
        ];

        // Log comprehensive audit trail before deletion
        $this->auditService->log(
            'payment_schedule.deleted',
            $paymentSchedule,
            $auditData,
            Auth::user(),
            $business
        );

        // Send payment schedule cancelled email before deleting
        $emailService = app(EmailService::class);
        $emailService->send($user, new PaymentScheduleCancelledEmail($user, $paymentSchedule), 'payment_schedule_cancelled');

        // Permanently delete the schedule (no soft delete)
        $paymentSchedule->delete();

        $route = 'payments.index';

        return redirect()->route($route)
            ->with('success', 'Payment schedule permanently deleted. All data has been logged in audit trail.');
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
        // Recalculate next run time when resuming (required — do not mark active without valid next_run_at)
        try {
            $nextRun = $this->cronService->getNextRunDate($paymentSchedule->frequency, now(config('app.timezone')));
            // Skip weekends and holidays
            $nextRun = $this->adjustToBusinessDay($nextRun);
            $paymentSchedule->update([
                'status' => 'active',
                'next_run_at' => $nextRun,
            ]);
        } catch (\Throwable $e) {
            Log::error('Unable to compute next run date when resuming payment schedule', [
                'schedule_id' => $paymentSchedule->id,
                'frequency' => $paymentSchedule->frequency,
                'error' => $e->getMessage(),
            ]);
            throw new \RuntimeException(
                'Unable to compute next run date for schedule: '.$e->getMessage().'. Fix the schedule or contact support.',
                0,
                $e
            );
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

    /**
     * Adjust a date to the next business day if it falls on a weekend or holiday.
     */
    private function adjustToBusinessDay(Carbon $date): Carbon
    {
        if ($this->holidayService->isBusinessDay($date)) {
            return $date;
        }

        $originalDate = $date->format('Y-m-d');
        $originalTime = $date->format('H:i');
        $adjustedDate = $this->holidayService->getNextBusinessDay($date);
        $adjustedDate->setTime((int) $date->format('H'), (int) $date->format('i'));

        Log::info('Payment schedule next run adjusted to skip weekend/holiday', [
            'original_date' => $originalDate,
            'adjusted_date' => $adjustedDate->format('Y-m-d'),
            'time' => $originalTime,
        ]);

        return $adjustedDate;
    }
}
