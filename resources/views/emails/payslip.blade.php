@extends('emails.layout')

@section('content')
    <h1>Your Payslip</h1>
    
    <p>Hello {{ $employee->name }},</p>
    
    <p>Your payslip for {{ $payrollJob->pay_period_start ? $payrollJob->pay_period_start->format('F Y') : now()->format('F Y') }} is attached to this email.</p>
    
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin: 24px 0;">
        <tr>
            <td style="background-color: #f5f5f5; border-radius: 8px; padding: 20px;">
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                    <tr>
                        <td style="padding-bottom: 12px;">
                            <strong>Pay Period:</strong> 
                            @if($payrollJob->pay_period_start && $payrollJob->pay_period_end)
                                {{ $payrollJob->pay_period_start->format('M d') }} - {{ $payrollJob->pay_period_end->format('M d, Y') }}
                            @else
                                {{ now()->format('F Y') }}
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td style="padding-bottom: 12px;">
                            <strong>Gross Salary:</strong> {{ $payrollJob->currency }} {{ number_format($payrollJob->gross_salary, 2) }}
                        </td>
                    </tr>
                    <tr>
                        <td style="padding-bottom: 12px;">
                            <strong>Net Salary:</strong> {{ $payrollJob->currency }} {{ number_format($payrollJob->net_salary, 2) }}
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <strong>Payment Date:</strong> {{ $payrollJob->processed_at ? $payrollJob->processed_at->format('F d, Y') : 'Pending' }}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
    
    <p class="text-muted" style="margin-top: 24px; font-size: 14px;">
        Please find your detailed payslip attached as a PDF. If you have any questions about your payslip, please contact your employer.
    </p>
    
    <p style="margin-top: 32px;">
        Regards,<br>
        <strong>{{ $business->name }}</strong>
    </p>
@endsection
