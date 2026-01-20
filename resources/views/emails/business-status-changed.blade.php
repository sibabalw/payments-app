@extends('emails.layout')

@section('content')
    <h1>Business Status Update</h1>
    
    <p>Your business status has been updated.</p>
    
    <div style="background-color: #f9fafb; border-left: 4px solid #1a1a1a; padding: 16px; margin: 24px 0; border-radius: 4px;">
        <p style="margin: 0 0 8px 0; font-weight: 600;">Business Details:</p>
        <p style="margin: 0 0 4px 0; color: #4a4a4a;"><strong>Business:</strong> {{ $businessData->name }}</p>
        <p style="margin: 0 0 4px 0; color: #4a4a4a;"><strong>Previous Status:</strong> {{ ucfirst($oldStatus) }}</p>
        <p style="margin: 0 0 4px 0; color: #4a4a4a;"><strong>New Status:</strong> {{ ucfirst($newStatus) }}</p>
        @if($reason)
        <p style="margin: 0; color: #4a4a4a;"><strong>Reason:</strong> {{ $reason }}</p>
        @endif
    </div>
    
    @if($newStatus === 'suspended' || $newStatus === 'banned')
    <div style="background-color: #fef2f2; border-left: 4px solid #dc2626; padding: 16px; margin: 24px 0; border-radius: 4px;">
        <p style="margin: 0; color: #991b1b;">
            <strong>Important:</strong> Your business account has been {{ $newStatus }}. 
            @if($newStatus === 'suspended')
            Some features may be limited. Please contact support for assistance.
            @else
            All payment activities have been halted. Please contact support immediately.
            @endif
        </p>
    </div>
    @endif
    
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin: 24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" cellspacing="0" cellpadding="0" border="0">
                    <tr>
                        <td align="center" style="background-color: #1a1a1a; border-radius: 8px; padding: 0;">
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
