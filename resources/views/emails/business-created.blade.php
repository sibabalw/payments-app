@extends('emails.layout')

@section('content')
    <h1>Business Created Successfully!</h1>
    
    <p>Your business profile has been created and is ready to use.</p>
    
    <div style="background-color: #f0fdf4; border-left: 4px solid #22c55e; padding: 16px; margin: 24px 0; border-radius: 4px;">
        <p style="margin: 0 0 8px 0; font-weight: 600; color: #166534;">Business Details:</p>
        <p style="margin: 0 0 4px 0; color: #4a4a4a;"><strong>Name:</strong> {{ $business->name }}</p>
        @if($business->email)
        <p style="margin: 0 0 4px 0; color: #4a4a4a;"><strong>Email:</strong> {{ $business->email }}</p>
        @endif
        @if($business->phone)
        <p style="margin: 0 0 4px 0; color: #4a4a4a;"><strong>Phone:</strong> {{ $business->phone }}</p>
        @endif
        <p style="margin: 0; color: #4a4a4a;"><strong>Status:</strong> {{ ucfirst($business->status) }}</p>
    </div>
    
    <p>You can now start using Swift Pay with this business:</p>
    <ul style="margin: 16px 0; padding-left: 24px; color: #4a4a4a;">
        <li style="margin-bottom: 8px;">Add receivers</li>
        <li style="margin-bottom: 8px;">Create payment schedules</li>
        <li style="margin-bottom: 8px;">Deposit funds into escrow</li>
        <li style="margin-bottom: 8px;">Manage your payments</li>
    </ul>
    
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin: 24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" cellspacing="0" cellpadding="0" border="0">
                    <tr>
                        <td align="center" style="background-color: #22c55e; border-radius: 8px; padding: 0;">
                            <a href="{{ route('businesses.index') }}" style="display: inline-block; padding: 14px 32px; font-size: 16px; font-weight: 600; color: #ffffff; text-decoration: none; border-radius: 8px;">
                                View Business
                            </a>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
@endsection
