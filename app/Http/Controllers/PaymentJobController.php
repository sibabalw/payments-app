<?php

namespace App\Http\Controllers;

use App\Models\PaymentJob;
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

        // Use JOIN instead of whereHas for better performance
        $query = PaymentJob::query()
            ->select(['payment_jobs.*'])
            ->join('payment_schedules', 'payment_jobs.payment_schedule_id', '=', 'payment_schedules.id')
            ->with([
                'paymentSchedule:id,business_id,name',
                'paymentSchedule.business:id,name',
                'recipient:id,name',
            ]);

        if ($scheduleId) {
            $query->where('payment_jobs.payment_schedule_id', $scheduleId);
        }

        if ($status) {
            $query->where('payment_jobs.status', $status);
        }

        if ($businessId) {
            $query->where('payment_schedules.business_id', $businessId);
        } else {
            $userBusinessIds = Auth::user()->businesses()->pluck('businesses.id')->toArray();
            $query->whereIn('payment_schedules.business_id', $userBusinessIds);
        }

        $jobs = $query->orderByDesc('payment_jobs.created_at')->paginate(20);

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
        $paymentJob->load(['paymentSchedule.business', 'recipient']);

        return Inertia::render('payments/job-show', [
            'job' => $paymentJob,
        ]);
    }
}
