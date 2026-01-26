@extends('emails.layout')

@section('content')
    <h1>New Login Detected</h1>
    
    <p>We noticed a new login to your Swift Pay account.</p>
    
    <div style="background-color: #f9fafb; border-left: 4px solid #1a1a1a; padding: 16px; margin: 24px 0; border-radius: 4px;">
        <p style="margin: 0 0 8px 0; font-weight: 600;">Login Details:</p>
        <p style="margin: 0 0 4px 0; color: #4a4a4a;"><strong>Time:</strong> {{ now()->format('F d, Y \a\t g:i A') }}</p>
        <p style="margin: 0 0 4px 0; color: #4a4a4a;"><strong>IP Address:</strong> {{ $ipAddress }}</p>
        <p style="margin: 0 0 4px 0; color: #4a4a4a;"><strong>Location:</strong> 
            @php
                $locationString = 'Unknown';
                if (isset($location) && is_array($location)) {
                    $city = trim($location['city'] ?? '');
                    $region = trim($location['region'] ?? '');
                    $country = trim($location['country'] ?? '');
                    
                    $locationParts = [];
                    if (!empty($city)) {
                        $locationParts[] = $city;
                    }
                    if (!empty($region)) {
                        $locationParts[] = $region;
                    }
                    if (!empty($country)) {
                        $locationParts[] = $country;
                    }
                    
                    if (!empty($locationParts)) {
                        $locationString = implode(', ', $locationParts);
                    } elseif (!empty($country)) {
                        $locationString = $country;
                    }
                }
            @endphp
            {{ $locationString }}
        </p>
        <p style="margin: 0; color: #4a4a4a;"><strong>Device:</strong> {{ Str::limit($userAgent, 100) }}</p>
    </div>
    
    <p>If this wasn't you, please secure your account immediately:</p>
    
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin: 24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" cellspacing="0" cellpadding="0" border="0">
                    <tr>
                        <td align="center" style="background-color: #dc2626; border-radius: 8px; padding: 0;">
                            <a href="{{ route('dashboard') }}" style="display: inline-block; padding: 14px 32px; font-size: 16px; font-weight: 600; color: #ffffff; text-decoration: none; border-radius: 8px;">
                                Secure My Account
                            </a>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
    
    <p class="text-muted" style="margin-top: 32px; font-size: 14px;">
        If you recognize this login, you can safely ignore this email.
    </p>
@endsection
