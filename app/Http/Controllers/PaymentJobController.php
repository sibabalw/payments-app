<?php

namespace App\Http\Controllers;

use App\Models\PaymentJob;
use App\Models\PaymentSchedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class PaymentJobController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        $businessId = $request->get('business_id') ?? Auth::user()->current_business_id ?? session('current_business_id');
        $scheduleId = $request->get('schedule_id');
        $status = $request->get('status');

        $query = PaymentJob::query()->with(['paymentSchedule.business', 'receiver']);

        if ($scheduleId) {
            $query->where('payment_schedule_id', $scheduleId);
        }

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

        return Inertia::render('payments/jobs', [
            'jobs' => $jobs,
            'filters' => [
                'schedule_id' => $scheduleId,
                'status' => $status,
                'business_id' => $businessId,
            ],
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(PaymentJob $paymentJob): Response
    {
        $paymentJob->load(['paymentSchedule.business', 'receiver']);

        return Inertia::render('payments/job-show', [
            'job' => $paymentJob,
        ]);
    }
}
