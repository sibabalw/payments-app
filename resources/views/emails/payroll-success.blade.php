@extends('emails.layout')

@section('content')
    <h1>Payroll Payment Successful!</h1>
    
    <p>Your payroll payment has been processed successfully.</p>
    
    <div style="background-color: #f0fdf4; border-left: 4px solid #22c55e; padding: 16px; margin: 24px 0; border-radius: 4px;">
        <p style="margin: 0 0 8px 0; font-weight: 600; color: #166534;">Payment Details:</p>
        <p style="margin: 0 0 4px 0; color: #4a4a4a;"><strong>Employee:</strong> {{ $payrollJob->employee->name }}</p>
        <p style="margin: 0 0 4px 0; color: #4a4a4a;"><strong>Gross Salary:</strong> {{ number_format($payrollJob->gross_salary, 2) }} {{ $payrollJob->currency }}</p>
        <p style="margin: 0 0 4px 0; color: #4a4a4a;"><strong>Net Salary:</strong> {{ number_format($payrollJob->net_salary, 2) }} {{ $payrollJob->currency }}</p>
        <p style="margin: 0 0 4px 0; color: #4a4a4a;"><strong>Schedule:</strong> {{ $payrollJob->payrollSchedule->name }}</p>
        @if($payrollJob->transaction_id)
        <p style="margin: 0 0 4px 0; color: #4a4a4a;"><strong>Transaction ID:</strong> {{ $payrollJob->transaction_id }}</p>
        @endif
        <p style="margin: 0; color: #4a4a4a;"><strong>Processed At:</strong> {{ $payrollJob->processed_at->format('F d, Y \a\t g:i A') }}</p>
    </div>
    
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin: 24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" cellspacing="0" cellpadding="0" border="0">
                    <tr>
                        <td align="center" style="background-color: #22c55e; border-radius: 8px; padding: 0;">
                            <a href="{{ route('payroll.jobs') }}" style="display: inline-block; padding: 14px 32px; font-size: 16px; font-weight: 600; color: #ffffff; text-decoration: none; border-radius: 8px;">
                                View Payroll Details
                            </a>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
@endsection
