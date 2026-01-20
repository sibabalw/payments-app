@extends('emails.layout')

@section('content')
    <h1>Your Employee Sign-In Code</h1>
    
    <p>Hello {{ $employee->name }},</p>
    
    <p>You have requested to sign in to your employee account. Use the verification code below to complete your sign-in:</p>
    
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin: 32px 0;">
        <tr>
            <td align="center">
                <table role="presentation" cellspacing="0" cellpadding="0" border="0">
                    <tr>
                        <td align="center" style="background-color: #f5f5f5; border-radius: 8px; padding: 24px 32px; border: 2px solid #1a1a1a;">
                            <span style="display: inline-block; font-size: 32px; font-weight: 700; color: #1a1a1a; letter-spacing: 8px; font-family: 'Courier New', monospace;">
                                {{ $otp }}
                            </span>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
    
    <p class="text-muted" style="margin-top: 24px; font-size: 14px;">
        This code will expire in 10 minutes. If you did not request this code, you can safely ignore this email.
    </p>
    
    <p style="margin-top: 32px;">
        Regards,<br>
        <strong>{{ $business->name ?? 'Swift Pay Team' }}</strong>
    </p>
@endsection
