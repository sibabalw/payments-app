@extends('emails.layout')

@section('content')
    <h1>Low Escrow Balance Alert</h1>
    
    <p>Your escrow account balance is running low and may not be sufficient for upcoming payments.</p>
    
    <div style="background-color: #fef2f2; border-left: 4px solid #dc2626; padding: 16px; margin: 24px 0; border-radius: 4px;">
        <p style="margin: 0 0 8px 0; font-weight: 600; color: #991b1b;">Balance Information:</p>
        <p style="margin: 0 0 4px 0; color: #4a4a4a;"><strong>Business:</strong> {{ $business->name }}</p>
        <p style="margin: 0 0 4px 0; color: #4a4a4a;"><strong>Current Balance:</strong> {{ number_format($currentBalance, 2) }}</p>
        <p style="margin: 0; color: #991b1b;"><strong>Required Amount:</strong> {{ number_format($requiredAmount, 2) }}</p>
    </div>
    
    <p>To avoid payment failures, please deposit additional funds into your escrow account.</p>
    
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin: 24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" cellspacing="0" cellpadding="0" border="0">
                    <tr>
                        <td align="center" style="background-color: #dc2626; border-radius: 8px; padding: 0;">
                            <a href="{{ route('dashboard') }}" style="display: inline-block; padding: 14px 32px; font-size: 16px; font-weight: 600; color: #ffffff; text-decoration: none; border-radius: 8px;">
                                Deposit Funds
                            </a>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
    
    <p class="text-muted" style="margin-top: 32px; font-size: 14px;">
        Payments will be automatically paused if your balance is insufficient.
    </p>
@endsection
