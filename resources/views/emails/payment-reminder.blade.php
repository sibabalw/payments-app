@extends('emails.layout')

@section('content')
    <h1>Payment Reminder</h1>
    
    <p>This is a reminder that a payment is scheduled to be processed in <strong>1 hour</strong>.</p>
    
    <div style="background-color: #f9fafb; border-left: 4px solid #1a1a1a; padding: 16px; margin: 24px 0; border-radius: 4px;">
        <p style="margin: 0 0 8px 0; font-weight: 600;">Payment Schedule Details:</p>
        <p style="margin: 0 0 4px 0; color: #4a4a4a;"><strong>Name:</strong> {{ $paymentSchedule->name }}</p>
        <p style="margin: 0 0 4px 0; color: #4a4a4a;"><strong>Amount:</strong> {{ number_format($paymentSchedule->amount, 2) }} {{ $paymentSchedule->currency }}</p>
        <p style="margin: 0 0 4px 0; color: #4a4a4a;"><strong>Scheduled Time:</strong> {{ $paymentSchedule->next_run_at->format('F d, Y \a\t g:i A') }}</p>
        <p style="margin: 0; color: #4a4a4a;"><strong>Business:</strong> {{ $paymentSchedule->business->name }}</p>
    </div>
    
    <p>Make sure you have sufficient funds in your escrow account to cover this payment.</p>
    
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin: 24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" cellspacing="0" cellpadding="0" border="0">
                    <tr>
                        <td align="center" style="background-color: #1a1a1a; border-radius: 8px; padding: 0;">
                            <a href="{{ route('payments.index') }}" style="display: inline-block; padding: 14px 32px; font-size: 16px; font-weight: 600; color: #ffffff; text-decoration: none; border-radius: 8px;">
                                View Payment Schedule
                            </a>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
@endsection
