@extends('emails.layout')

@section('content')
    <h1>Payroll Payment Failed</h1>
    
    <p>We were unable to process your payroll payment after multiple attempts.</p>
    
    <div style="background-color: #fef2f2; border-left: 4px solid #dc2626; padding: 16px; margin: 24px 0; border-radius: 4px;">
        <p style="margin: 0 0 8px 0; font-weight: 600; color: #991b1b;">Payment Details:</p>
        <p style="margin: 0 0 4px 0; color: #4a4a4a;"><strong>Employee:</strong> {{ $payrollJob->employee->name }}</p>
        <p style="margin: 0 0 4px 0; color: #4a4a4a;"><strong>Gross Salary:</strong> {{ number_format($payrollJob->gross_salary, 2) }} {{ $payrollJob->currency }}</p>
        <p style="margin: 0 0 4px 0; color: #4a4a4a;"><strong>Schedule:</strong> {{ $payrollJob->payrollSchedule->name }}</p>
        @if($payrollJob->error_message)
        <p style="margin: 0; color: #991b1b;"><strong>Error:</strong> {{ $payrollJob->error_message }}</p>
        @endif
    </div>
    
    <p>Please review the error message above and take appropriate action. You may need to:</p>
    <ul style="margin: 16px 0; padding-left: 24px; color: #4a4a4a;">
        <li style="margin-bottom: 8px;">Check your escrow balance</li>
        <li style="margin-bottom: 8px;">Verify employee payment details</li>
        <li style="margin-bottom: 8px;">Contact support if the issue persists</li>
    </ul>
    
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin: 24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" cellspacing="0" cellpadding="0" border="0">
                    <tr>
                        <td align="center" style="background-color: #dc2626; border-radius: 8px; padding: 0;">
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
