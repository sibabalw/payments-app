@extends('emails.layout')

@section('content')
    <h1>Payroll Schedule Cancelled</h1>
    
    <p>Your payroll schedule has been cancelled.</p>
    
    <div style="background-color: #f9fafb; border-left: 4px solid #1a1a1a; padding: 16px; margin: 24px 0; border-radius: 4px;">
        <p style="margin: 0 0 8px 0; font-weight: 600;">Schedule Details:</p>
        <p style="margin: 0 0 4px 0; color: #4a4a4a;"><strong>Name:</strong> {{ $payrollSchedule->name }}</p>
        <p style="margin: 0 0 4px 0; color: #4a4a4a;"><strong>Frequency:</strong> {{ $payrollSchedule->frequency }}</p>
        <p style="margin: 0 0 4px 0; color: #4a4a4a;"><strong>Type:</strong> {{ ucfirst(str_replace('_', ' ', $payrollSchedule->schedule_type)) }}</p>
    </div>
    
    <p>No further payroll will be processed for this schedule. You can create a new schedule at any time.</p>
    
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin: 24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" cellspacing="0" cellpadding="0" border="0">
                    <tr>
                        <td align="center" style="background-color: #1a1a1a; border-radius: 8px; padding: 0;">
                            <a href="{{ route('payroll.index') }}" style="display: inline-block; padding: 14px 32px; font-size: 16px; font-weight: 600; color: #ffffff; text-decoration: none; border-radius: 8px;">
                                View Payroll Schedules
                            </a>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
@endsection
