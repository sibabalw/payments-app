<?php

namespace App\Http\Controllers;

use App\Models\PayrollJob;
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
        $employeeId = $request->get('employee_id');

        $query = PayrollJob::query()->with(['payrollSchedule.business', 'employee']);

        if ($scheduleId) {
            $query->where('payroll_schedule_id', $scheduleId);
        }

        if ($status) {
            $query->where('status', $status);
        }

        if ($employeeId) {
            $query->where('employee_id', $employeeId);
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

        $employees = \App\Models\Employee::query()
            ->when($businessId, function ($q) use ($businessId) {
                $q->where('business_id', $businessId);
            })
            ->get();

        return Inertia::render('payroll/jobs', [
            'jobs' => $jobs,
            'employees' => $employees,
            'filters' => [
                'schedule_id' => $scheduleId,
                'status' => $status,
                'business_id' => $businessId,
                'employee_id' => $employeeId,
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
