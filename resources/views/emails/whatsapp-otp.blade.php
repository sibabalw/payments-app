@extends('emails.layout')

@section('content')
    <h1>WhatsApp Verification Code</h1>
    
    <p>Hi {{ $user->name }},</p>
    
    <p>You're trying to log in to Swift Pay via WhatsApp. Here's your verification code:</p>
    
    <div style="background-color: #f3f4f6; border-radius: 8px; padding: 24px; margin: 24px 0; text-align: center;">
        <p style="font-size: 32px; font-weight: bold; letter-spacing: 8px; margin: 0; color: #1f2937;">{{ $otp }}</p>
    </div>
    
    <p>Enter this code in your WhatsApp chat to complete the login.</p>
    
    <div style="background-color: #fef3c7; border-left: 4px solid #f59e0b; padding: 16px; margin: 24px 0; border-radius: 4px;">
        <p style="margin: 0; color: #92400e;">
            <strong>Security Notice:</strong> This code will expire in 10 minutes. If you didn't request this code, please ignore this email.
        </p>
    </div>
    
    <p class="text-muted" style="margin-top: 32px; font-size: 14px;">
        Phone number requesting access: {{ $phoneNumber }}
    </p>
@endsection
