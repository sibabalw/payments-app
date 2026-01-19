<?php

namespace App\Mail;

use App\Models\Employee;
use App\Models\PayrollJob;
use Barryvdh\DomPDF\Facade\Pdf as PDF;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PayslipEmail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public Employee $employee,
        public PayrollJob $payrollJob
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $period = $this->payrollJob->pay_period_start
            ? $this->payrollJob->pay_period_start->format('F Y')
            : now()->format('F Y');

        return new Envelope(
            subject: "Your Payslip - {$period}",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        // Load relationships
        $this->payrollJob->load([
            'payrollSchedule.business',
            'employee.business',
            'escrowDeposit',
            'releasedBy',
        ]);

        // Get custom deductions
        $customDeductions = $this->employee->getAllDeductions();
        $customDeductionsWithAmounts = $customDeductions->map(function ($deduction) {
            $amount = $deduction->calculateAmount($this->payrollJob->gross_salary);

            return [
                'id' => $deduction->id,
                'name' => $deduction->name,
                'type' => $deduction->type,
                'amount' => $amount,
                'original_amount' => $deduction->amount,
            ];
        })->values();

        return new Content(
            view: 'emails.payslip',
            with: [
                'employee' => $this->employee,
                'payrollJob' => $this->payrollJob,
                'business' => $this->payrollJob->payrollSchedule->business,
                'customDeductions' => $customDeductionsWithAmounts,
                'user' => (object) ['email' => $this->employee->email], // For email layout compatibility
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        // Load relationships
        $this->payrollJob->load([
            'payrollSchedule.business',
            'employee.business',
            'escrowDeposit',
            'releasedBy',
        ]);

        // Get custom deductions
        $customDeductions = $this->employee->getAllDeductions();
        $customDeductionsWithAmounts = $customDeductions->map(function ($deduction) {
            $amount = $deduction->calculateAmount($this->payrollJob->gross_salary);

            return [
                'id' => $deduction->id,
                'name' => $deduction->name,
                'type' => $deduction->type,
                'amount' => $amount,
                'original_amount' => $deduction->amount,
            ];
        })->values();

        $data = [
            'job' => $this->payrollJob,
            'employee' => $this->employee,
            'business' => $this->payrollJob->payrollSchedule->business,
            'custom_deductions' => $customDeductionsWithAmounts,
        ];

        // Generate PDF
        $pdf = PDF::loadView('payslips.pdf', $data);

        $filename = 'payslip-'.$this->employee->name.'-'.
                   ($this->payrollJob->pay_period_start
                       ? $this->payrollJob->pay_period_start->format('Y-m')
                       : now()->format('Y-m')).'.pdf';

        return [
            Attachment::fromData(fn () => $pdf->output(), $filename)
                ->withMime('application/pdf'),
        ];
    }
}
