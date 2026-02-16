<?php

namespace App\Http\Controllers;

use App\Models\Employee;
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

        // Use JOIN instead of whereHas for better performance
        $query = PayrollJob::query()
            ->select(['payroll_jobs.*'])
            ->join('payroll_schedules', 'payroll_jobs.payroll_schedule_id', '=', 'payroll_schedules.id')
            ->with([
                'payrollSchedule:id,business_id,name',
                'payrollSchedule.business:id,name',
                'employee:id,name,email',
            ]);

        if ($scheduleId) {
            $query->where('payroll_jobs.payroll_schedule_id', $scheduleId);
        }

        if ($status) {
            $query->where('payroll_jobs.status', $status);
        }

        if ($employeeId) {
            $query->where('payroll_jobs.employee_id', $employeeId);
        }

        if ($businessId) {
            $query->where('payroll_schedules.business_id', $businessId);
        } else {
            $userBusinessIds = Auth::user()->businesses()->pluck('businesses.id')->toArray();
            $query->whereIn('payroll_schedules.business_id', $userBusinessIds);
        }

        $jobs = $query->orderByDesc('payroll_jobs.created_at')->paginate(20);

        $employees = Employee::query()
            ->when($businessId, fn ($q) => $q->where('business_id', $businessId))
            ->select(['id', 'name', 'business_id'])
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
