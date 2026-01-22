<?php

namespace App\Http\Controllers;

use App\Models\PayrollJob;
use Barryvdh\DomPDF\Facade\Pdf as PDF;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class PayslipController extends Controller
{
    /**
     * List all payslips for an employee
     */
    public function index(Request $request): Response
    {
        $employeeId = $request->get('employee_id');
        $businessId = $request->get('business_id') ?? Auth::user()->current_business_id ?? session('current_business_id');

        // Use JOIN instead of whereHas for better performance
        $query = PayrollJob::query()
            ->select(['payroll_jobs.*'])
            ->join('payroll_schedules', 'payroll_jobs.payroll_schedule_id', '=', 'payroll_schedules.id')
            ->where('payroll_jobs.status', 'succeeded')
            ->with(['payrollSchedule.business', 'employee']);

        if ($employeeId) {
            $query->where('payroll_jobs.employee_id', $employeeId);
        }

        if ($businessId) {
            $query->where('payroll_schedules.business_id', $businessId);
        } else {
            $userBusinessIds = Auth::user()->businesses()->pluck('businesses.id')->toArray();
            $query->whereIn('payroll_schedules.business_id', $userBusinessIds);
        }

        $payslips = $query->orderByDesc('payroll_jobs.pay_period_start')->paginate(20);

        $employees = \App\Models\Employee::query()
            ->when($businessId, fn ($q) => $q->where('business_id', $businessId))
            ->get();

        return Inertia::render('payslips/index', [
            'payslips' => $payslips,
            'employees' => $employees,
            'filters' => [
                'employee_id' => $employeeId,
                'business_id' => $businessId,
            ],
        ]);
    }

    /**
     * Display payslip view
     */
    public function show(PayrollJob $payrollJob): Response
    {
        $payrollJob->load([
            'payrollSchedule.business',
            'employee.business',
            'escrowDeposit',
            'releasedBy',
        ]);

        // Get custom deductions for this employee and calculate amounts
        $customDeductions = $payrollJob->employee->getAllDeductions();
        $customDeductionsWithAmounts = $customDeductions->map(function ($deduction) use ($payrollJob) {
            $amount = $deduction->calculateAmount($payrollJob->gross_salary);

            return [
                'id' => $deduction->id,
                'name' => $deduction->name,
                'type' => $deduction->type,
                'amount' => $amount,
                'original_amount' => $deduction->amount, // The configured percentage or fixed amount
            ];
        })->values();

        return Inertia::render('payslips/show', [
            'payslip' => [
                'job' => $payrollJob,
                'employee' => $payrollJob->employee,
                'business' => $payrollJob->payrollSchedule->business,
                'custom_deductions' => $customDeductionsWithAmounts,
            ],
        ]);
    }

    /**
     * Generate and download PDF payslip
     */
    public function download(PayrollJob $payrollJob)
    {
        $payrollJob->load([
            'payrollSchedule.business',
            'employee.business',
            'escrowDeposit',
            'releasedBy',
        ]);

        $customDeductions = $payrollJob->employee->getAllDeductions();
        $customDeductionsWithAmounts = $customDeductions->map(function ($deduction) use ($payrollJob) {
            $amount = $deduction->calculateAmount($payrollJob->gross_salary);

            return [
                'id' => $deduction->id,
                'name' => $deduction->name,
                'type' => $deduction->type,
                'amount' => $amount,
                'original_amount' => $deduction->amount,
            ];
        })->values();

        $data = [
            'job' => $payrollJob,
            'employee' => $payrollJob->employee,
            'business' => $payrollJob->payrollSchedule->business,
            'custom_deductions' => $customDeductionsWithAmounts,
        ];

        // Generate PDF using DomPDF
        $pdf = PDF::loadView('payslips.pdf', $data);

        $filename = 'payslip-'.$payrollJob->employee->name.'-'.
                   ($payrollJob->pay_period_start ? $payrollJob->pay_period_start->format('Y-m') : date('Y-m')).'.pdf';

        return $pdf->download($filename);
    }

    /**
     * Generate PDF payslip and return as response (for preview)
     */
    public function pdf(PayrollJob $payrollJob)
    {
        $payrollJob->load([
            'payrollSchedule.business',
            'employee.business',
            'escrowDeposit',
            'releasedBy',
        ]);

        $customDeductions = $payrollJob->employee->getAllDeductions();
        $customDeductionsWithAmounts = $customDeductions->map(function ($deduction) use ($payrollJob) {
            $amount = $deduction->calculateAmount($payrollJob->gross_salary);

            return [
                'id' => $deduction->id,
                'name' => $deduction->name,
                'type' => $deduction->type,
                'amount' => $amount,
                'original_amount' => $deduction->amount,
            ];
        })->values();

        $data = [
            'job' => $payrollJob,
            'employee' => $payrollJob->employee,
            'business' => $payrollJob->payrollSchedule->business,
            'custom_deductions' => $customDeductionsWithAmounts,
        ];

        $pdf = PDF::loadView('payslips.pdf', $data);

        return response($pdf->output(), 200)
            ->header('Content-Type', 'application/pdf');
    }

    /**
     * Get all payslips for a specific employee
     */
    public function employeePayslips(\App\Models\Employee $employee, Request $request): Response
    {
        $payslips = PayrollJob::where('employee_id', $employee->id)
            ->where('status', 'succeeded')
            ->with(['payrollSchedule.business'])
            ->latest('pay_period_start')
            ->paginate(20);

        return Inertia::render('payslips/index', [
            'payslips' => $payslips,
            'employees' => collect([$employee]),
            'filters' => [
                'employee_id' => $employee->id,
                'business_id' => $employee->business_id,
            ],
            'employee' => $employee,
        ]);
    }
}
