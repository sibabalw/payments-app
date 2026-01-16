<?php

namespace App\Http\Controllers;

use App\Models\PayrollJob;
use App\Models\PayrollSchedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class PayrollJobController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        $businessId = $request->get('business_id') ?? Auth::user()->current_business_id ?? session('current_business_id');
        $scheduleId = $request->get('schedule_id');
        $status = $request->get('status');

        $query = PayrollJob::query()->with(['payrollSchedule.business', 'employee']);

        if ($scheduleId) {
            $query->where('payroll_schedule_id', $scheduleId);
        }

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
                'schedule_id' => $scheduleId,
                'status' => $status,
                'business_id' => $businessId,
            ],
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(PayrollJob $payrollJob): Response
    {
        $payrollJob->load(['payrollSchedule.business', 'employee', 'escrowDeposit', 'releasedBy']);

        return Inertia::render('payroll/job-show', [
            'job' => $payrollJob,
        ]);
    }
}
