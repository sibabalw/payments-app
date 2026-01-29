@extends('emails.layout')

@section('content')
    <h1>Escrow Deposit Confirmed</h1>
    
    <p>Your escrow deposit has been confirmed and credited to your account.</p>
    
    <div style="background-color: #f0fdf4; border-left: 4px solid #22c55e; padding: 16px; margin: 24px 0; border-radius: 4px;">
        <p style="margin: 0 0 8px 0; font-weight: 600; color: #166534;">Deposit Details:</p>
        <p style="margin: 0 0 4px 0; color: #4a4a4a;"><strong>Amount:</strong> {{ number_format($deposit->amount, 2) }} {{ $deposit->currency }}</p>
        <p style="margin: 0 0 4px 0; color: #4a4a4a;"><strong>Fee:</strong> {{ number_format($deposit->fee_amount, 2) }} {{ $deposit->currency }}</p>
        <p style="margin: 0 0 4px 0; color: #4a4a4a;"><strong>Credited Amount:</strong> {{ number_format($deposit->authorized_amount, 2) }} {{ $deposit->currency }}</p>
        @if($deposit->bank_reference)
        <p style="margin: 0 0 4px 0; color: #4a4a4a;"><strong>Bank Reference:</strong> {{ $deposit->bank_reference }}</p>
        @endif
        <p style="margin: 0; color: #4a4a4a;"><strong>Date:</strong> {{ $deposit->completed_at->format('F j, Y g:i A') }}</p>
    </div>
    
    <p>Your escrow balance has been updated and you can now use these funds for payments and payroll.</p>
    
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin: 24px 0;">
        <tr>
            <td align="center">
                <a href="{{ route('dashboard') }}" style="display: inline-block; padding: 12px 24px; background-color: #2563eb; color: #ffffff; text-decoration: none; border-radius: 6px; font-weight: 600;">View Dashboard</a>
            </td>
        </tr>
    </table>
    
    <p style="color: #6b7280; font-size: 14px; margin-top: 24px;">If you have any questions about this deposit, please contact our support team.</p>
@endsection
