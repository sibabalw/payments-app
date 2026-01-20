@extends('emails.layout')

@section('content')
    <h1>Escrow Balance Warning</h1>
    
    <p>Hi {{ $user->name }},</p>
    
    <p>This is a daily reminder that your upcoming scheduled payments and payroll for <strong>{{ $business->name }}</strong> exceed your current escrow balance.</p>
    
    <div style="background-color: #fef2f2; border-left: 4px solid #dc2626; padding: 16px; margin: 24px 0; border-radius: 4px;">
        <p style="margin: 0 0 12px 0; font-weight: 600; color: #991b1b; font-size: 16px;">Balance Summary</p>
        
        <table style="width: 100%; border-collapse: collapse;">
            <tr>
                <td style="padding: 8px 0; color: #4a4a4a; border-bottom: 1px solid #fecaca;">Current Escrow Balance:</td>
                <td style="padding: 8px 0; text-align: right; font-weight: 600; color: #4a4a4a; border-bottom: 1px solid #fecaca;">R {{ number_format($currentBalance, 2) }}</td>
            </tr>
            <tr>
                <td style="padding: 8px 0; color: #4a4a4a; border-bottom: 1px solid #fecaca;">Upcoming Payments (7 days):</td>
                <td style="padding: 8px 0; text-align: right; color: #4a4a4a; border-bottom: 1px solid #fecaca;">R {{ number_format($upcomingPayments, 2) }}</td>
            </tr>
            <tr>
                <td style="padding: 8px 0; color: #4a4a4a; border-bottom: 1px solid #fecaca;">Upcoming Payroll (7 days):</td>
                <td style="padding: 8px 0; text-align: right; color: #4a4a4a; border-bottom: 1px solid #fecaca;">R {{ number_format($upcomingPayroll, 2) }}</td>
            </tr>
            <tr>
                <td style="padding: 8px 0; color: #4a4a4a; font-weight: 600; border-bottom: 1px solid #fecaca;">Total Required:</td>
                <td style="padding: 8px 0; text-align: right; font-weight: 600; color: #4a4a4a; border-bottom: 1px solid #fecaca;">R {{ number_format($totalRequired, 2) }}</td>
            </tr>
            <tr>
                <td style="padding: 12px 0; color: #991b1b; font-weight: 700; font-size: 16px;">Shortfall:</td>
                <td style="padding: 12px 0; text-align: right; font-weight: 700; color: #991b1b; font-size: 16px;">R {{ number_format($shortfall, 2) }}</td>
            </tr>
        </table>
    </div>
    
    <div style="background-color: #fffbeb; border-left: 4px solid #f59e0b; padding: 16px; margin: 24px 0; border-radius: 4px;">
        <p style="margin: 0; color: #92400e;">
            <strong>Action Required:</strong> Please deposit at least <strong>R {{ number_format($shortfall, 2) }}</strong> into your escrow account to ensure all scheduled payments and payroll can be processed without interruption.
        </p>
    </div>
    
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin: 24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" cellspacing="0" cellpadding="0" border="0">
                    <tr>
                        <td align="center" style="background-color: #dc2626; border-radius: 8px; padding: 0;">
                            <a href="{{ route('escrow.deposit.index') }}" style="display: inline-block; padding: 14px 32px; font-size: 16px; font-weight: 600; color: #ffffff; text-decoration: none; border-radius: 8px;">
                                Deposit Funds Now
                            </a>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
    
    <p style="margin-top: 24px; color: #6b7280; font-size: 14px;">
        <strong>What happens if I don't deposit?</strong><br>
        Scheduled payments and payroll runs will fail if there are insufficient funds in your escrow account. This may result in:
    </p>
    <ul style="color: #6b7280; font-size: 14px; margin-top: 8px;">
        <li>Late payments to recipients and employees</li>
        <li>Failed payment notifications</li>
        <li>Potential compliance issues with tax obligations</li>
    </ul>
    
    <p class="text-muted" style="margin-top: 32px; font-size: 13px; color: #9ca3af;">
        This is an automated daily reminder. You will continue to receive this notification until your escrow balance is sufficient to cover upcoming obligations.
    </p>
@endsection
