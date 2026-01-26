@extends('emails.layout')

@section('content')
    <h1>You have been added as an administrator</h1>

    <p>Hi {{ $user->name }},</p>

    <p>You have been added as an administrator on {{ config('app.name') }}.</p>

    @if($addedBy)
        <p>{{ $addedBy->name }} ({{ $addedBy->email }}) has granted you admin access.</p>
    @endif

    <p>As an administrator you can:</p>
    <ul style="margin: 16px 0; padding-left: 24px; color: #4a4a4a;">
        <li style="margin-bottom: 8px;">Access the admin dashboard</li>
        <li style="margin-bottom: 8px;">Manage users and businesses</li>
        <li style="margin-bottom: 8px;">View escrow balances and system health</li>
        <li style="margin-bottom: 8px;">Configure system settings and view audit logs</li>
    </ul>

    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin: 24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" cellspacing="0" cellpadding="0" border="0">
                    <tr>
                        <td align="center" style="background-color: #1a1a1a; border-radius: 8px; padding: 0;">
                            <a href="{{ url('/admin') }}" style="display: inline-block; padding: 14px 32px; font-size: 16px; font-weight: 600; color: #ffffff; text-decoration: none; border-radius: 8px;">
                                Go to Admin Panel
                            </a>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <p class="text-muted" style="margin-top: 32px; font-size: 14px;">
        If you did not expect this, please contact your team or support.
    </p>
@endsection
