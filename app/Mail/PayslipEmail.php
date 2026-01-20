<?php

namespace App\Mail;

use App\Models\BusinessTemplate;
use App\Models\Employee;
use App\Models\PayrollJob;
use App\Services\TemplateService;
use Barryvdh\DomPDF\Facade\Pdf as PDF;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
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
        // Load business relationship
        $this->payrollJob->loadMissing('payrollSchedule.business');
        $business = $this->payrollJob->payrollSchedule->business ?? $this->employee->business;

        // Get business email and name, fallback to Swift Pay defaults
        $fromEmail = $business->email ?? config('mail.from.address');
        $fromName = $business->name ?? config('mail.from.name');

        $period = $this->payrollJob->pay_period_start
            ? $this->payrollJob->pay_period_start->format('F Y')
            : now()->format('F Y');

        return new Envelope(
            from: new Address($fromEmail, $fromName),
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

        $business = $this->payrollJob->payrollSchedule->business ?? $this->employee->business;

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

        // Check for custom template
        $templateService = app(TemplateService::class);
        $customTemplate = $templateService->getBusinessTemplate(
            $business->id,
            BusinessTemplate::TYPE_EMAIL_PAYSLIP
        );

        if ($customTemplate && $customTemplate->compiled_html) {
            $period = $this->payrollJob->pay_period_start
                ? $this->payrollJob->pay_period_start->format('F Y')
                : now()->format('F Y');

            $html = $templateService->renderTemplate($customTemplate->compiled_html, [
                'subject' => 'Your Payslip',
                'business_name' => $business->name,
                'business_logo' => $business->logo ?? '',
                'year' => date('Y'),
                'employee_name' => $this->employee->name,
                'pay_period' => $period,
                'currency' => $this->payrollJob->currency,
                'gross_salary' => number_format($this->payrollJob->gross_salary, 2),
                'net_salary' => number_format($this->payrollJob->net_salary, 2),
                'payment_date' => $this->payrollJob->processed_at?->format('F d, Y') ?? 'Pending',
            ]);

            return new Content(
                view: 'emails.custom',
                with: ['html' => $html],
            );
        }

        return new Content(
            view: 'emails.payslip',
            with: [
                'employee' => $this->employee,
                'payrollJob' => $this->payrollJob,
                'business' => $business,
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
