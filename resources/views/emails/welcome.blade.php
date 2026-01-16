@extends('emails.layout')

@section('content')
    <h1>Welcome to Swift Pay, {{ $user->name }}!</h1>
    
    <p>Thank you for creating your account. We're excited to have you on board!</p>
    
    <p>Swift Pay makes it easy to manage your payments, payroll, and business finances all in one place.</p>
    
    <h2>Get Started</h2>
    <p>Here's what you can do next:</p>
    <ul style="margin: 16px 0; padding-left: 24px; color: #4a4a4a;">
        <li style="margin-bottom: 8px;">Add your first business profile</li>
        <li style="margin-bottom: 8px;">Set up payment schedules</li>
        <li style="margin-bottom: 8px;">Add receivers for your payments</li>
        <li style="margin-bottom: 8px;">Deposit funds into your escrow account</li>
    </ul>
    
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin: 24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" cellspacing="0" cellpadding="0" border="0">
                    <tr>
                        <td align="center" style="background-color: #1a1a1a; border-radius: 8px; padding: 0;">
                            <a href="{{ route('dashboard') }}" style="display: inline-block; padding: 14px 32px; font-size: 16px; font-weight: 600; color: #ffffff; text-decoration: none; border-radius: 8px;">
                                Go to Dashboard
                            </a>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
    
    <p class="text-muted" style="margin-top: 32px; font-size: 14px;">
        If you have any questions, feel free to reach out to our support team. We're here to help!
    </p>
@endsection
