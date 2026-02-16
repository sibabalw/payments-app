@extends('emails.layout')

@section('content')
    <h1>Verify Your Email Address</h1>
    
    <p>Hello {{ $user->name }},</p>
    
    <p>Thank you for registering with SwiftPay. This is a verification email to confirm your email address and complete your registration.</p>
    
    <p>Please click the button below to verify your email address and activate your account:</p>
    
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin: 24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" cellspacing="0" cellpadding="0" border="0">
                    <tr>
                        <td align="center" style="background-color: #1a1a1a; border-radius: 8px; padding: 0;">
                            <a href="{{ $verificationUrl }}" style="display: inline-block; padding: 14px 32px; font-size: 16px; font-weight: 600; color: #ffffff; text-decoration: none; border-radius: 8px;">
                                Verify Email Address
                            </a>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
    
    <p class="text-muted" style="margin-top: 24px; font-size: 14px;">
        If you're having trouble clicking the button, copy and paste the URL below into your web browser:
    </p>
    
    <p class="text-muted" style="margin-top: 8px; font-size: 12px; word-break: break-all;">
        {{ $verificationUrl }}
    </p>
    
    <p class="text-muted" style="margin-top: 24px; font-size: 14px;">
        If you did not create an account with SwiftPay, you can safely ignore this email.
    </p>
    
    <p style="margin-top: 32px;">
        Regards,<br>
        <strong>SwiftPay Team</strong>
    </p>
@endsection
