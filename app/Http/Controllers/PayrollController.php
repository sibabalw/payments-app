<?php

namespace App\Http\Controllers;

use App\Models\PaymentSchedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class PayrollController extends PaymentScheduleController
{
    /**
     * Display a listing of payroll schedules.
     */
    public function index(Request $request): Response
    {
        $businessId = $request->get('business_id') ?? Auth::user()->current_business_id ?? session('current_business_id');
        $status = $request->get('status');

        $query = PaymentSchedule::query()
            ->ofType('payroll')
            ->with(['business', 'receivers']);

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
        
        $receivers = [];
        if ($businessId) {
            $receivers = \App\Models\Receiver::where('business_id', $businessId)->get();
        }

        return Inertia::render('payroll/create', [
            'businesses' => $businesses,
            'receivers' => $receivers,
            'selectedBusinessId' => $businessId,
        ]);
    }

    /**
     * Store a newly created payroll schedule.
     */
    public function store(Request $request)
    {
        $request->merge(['type' => 'payroll']);
        return parent::store($request);
    }

    /**
     * Show the form for editing the specified payroll schedule.
     */
    public function edit(PaymentSchedule $paymentSchedule): Response
    {
        if ($paymentSchedule->type !== 'payroll') {
            abort(404);
        }

        $businesses = Auth::user()->businesses()->get();
        $receivers = \App\Models\Receiver::where('business_id', $paymentSchedule->business_id)->get();

        // Parse cron expression to extract date/time for editing
        $parsed = $this->cronParser->parse($paymentSchedule->frequency);
        
        $schedule = $paymentSchedule->load(['business', 'receivers']);
        if ($parsed) {
            $schedule->scheduled_date = $parsed['date'];
            $schedule->scheduled_time = $parsed['time'];
            $schedule->parsed_frequency = $parsed['frequency'];
        }

        return Inertia::render('payroll/edit', [
            'schedule' => $schedule,
            'businesses' => $businesses,
            'receivers' => $receivers,
        ]);
    }

    /**
     * Update the specified payroll schedule.
     */
    public function update(Request $request, PaymentSchedule $paymentSchedule)
    {
        if ($paymentSchedule->type !== 'payroll') {
            abort(404);
        }

        return parent::update($request, $paymentSchedule);
    }

    /**
     * Display payment jobs for payroll schedules.
     */
    public function jobs(Request $request): Response
    {
        $businessId = $request->get('business_id') ?? Auth::user()->current_business_id ?? session('current_business_id');
        $status = $request->get('status');

        $query = \App\Models\PaymentJob::query()
            ->whereHas('paymentSchedule', function ($q) {
                $q->ofType('payroll');
            })
            ->with(['paymentSchedule.business', 'receiver']);

        if ($status) {
            $query->where('status', $status);
        }

        if ($businessId) {
            $query->whereHas('paymentSchedule', function ($q) use ($businessId) {
                $q->where('business_id', $businessId);
            });
        } else {
            $userBusinessIds = Auth::user()->businesses()->pluck('businesses.id');
            $query->whereHas('paymentSchedule', function ($q) use ($userBusinessIds) {
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
