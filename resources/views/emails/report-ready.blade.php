@extends('emails.layout')

@section('content')
    <h1>Your Report is Ready</h1>

    <p>Your {{ ucwords(str_replace('_', ' ', $reportGeneration->report_type)) }} report is attached to this email. You can open and view it directly in your email.</p>

    <div style="background-color: #f0f9ff; border-left: 4px solid #3b82f6; padding: 16px; margin: 24px 0; border-radius: 4px;">
        <p style="margin: 0 0 8px 0; font-weight: 600; color: #1e40af;">Report details</p>
        <p style="margin: 0 0 4px 0; color: #4a4a4a;"><strong>Type:</strong> {{ ucwords(str_replace('_', ' ', $reportGeneration->report_type)) }}</p>
        <p style="margin: 0 0 4px 0; color: #4a4a4a;"><strong>Format:</strong> {{ strtoupper($reportGeneration->format) }}</p>
        <p style="margin: 0 0 4px 0; color: #4a4a4a;"><strong>Attachment:</strong> {{ $reportGeneration->filename }}</p>
        @if($business)
        <p style="margin: 0 0 4px 0; color: #4a4a4a;"><strong>Business:</strong> {{ $business->name }}</p>
        @endif
        <p style="margin: 0; color: #4a4a4a;"><strong>Generated:</strong> {{ $reportGeneration->completed_at?->format('F d, Y \a\t g:i A') ?? '—' }}</p>
    </div>

    <p style="color: #6b7280; font-size: 14px; margin-top: 24px;">
        Open the attached file above to view your report. PDFs can be viewed in your email client; CSV/Excel files can be opened in a spreadsheet app.
    </p>
@endsection
