@extends('emails.layout')

@section('content')
    <h1>Your Report is Ready!</h1>
    
    <p>Your {{ ucwords(str_replace('_', ' ', $reportGeneration->report_type)) }} report has been generated and is ready for download.</p>
    
    <div style="background-color: #f0f9ff; border-left: 4px solid #3b82f6; padding: 16px; margin: 24px 0; border-radius: 4px;">
        <p style="margin: 0 0 8px 0; font-weight: 600; color: #1e40af;">Report Details:</p>
        <p style="margin: 0 0 4px 0; color: #4a4a4a;"><strong>Type:</strong> {{ ucwords(str_replace('_', ' ', $reportGeneration->report_type)) }}</p>
        <p style="margin: 0 0 4px 0; color: #4a4a4a;"><strong>Format:</strong> {{ strtoupper($reportGeneration->format) }}</p>
        <p style="margin: 0 0 4px 0; color: #4a4a4a;"><strong>Filename:</strong> {{ $reportGeneration->filename }}</p>
        @if($business)
        <p style="margin: 0 0 4px 0; color: #4a4a4a;"><strong>Business:</strong> {{ $business->name }}</p>
        @endif
        <p style="margin: 0; color: #4a4a4a;"><strong>Generated At:</strong> {{ $reportGeneration->completed_at->format('F d, Y \a\t g:i A') }}</p>
    </div>
    
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin: 24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" cellspacing="0" cellpadding="0" border="0">
                    <tr>
                        <td align="center" style="background-color: #3b82f6; border-radius: 8px; padding: 0;">
                            <a href="{{ $downloadUrl }}" style="display: inline-block; padding: 14px 32px; font-size: 16px; font-weight: 600; color: #ffffff; text-decoration: none; border-radius: 8px;">
                                Download Report
                            </a>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
    
    <p style="color: #6b7280; font-size: 14px; margin-top: 24px;">
        <strong>Note:</strong> This download link will expire in 7 days. Please download your report promptly.
    </p>
@endsection
