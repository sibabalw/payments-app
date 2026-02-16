@extends('emails.layout')

@section('content')
    <h1>Application Error Detected</h1>
    
    <p>A {{ $errorLog->is_admin_error ? 'admin' : 'user' }} error has been detected in the application.</p>
    
    <div style="background-color: #fef2f2; border-left: 4px solid #dc2626; padding: 16px; margin: 24px 0; border-radius: 4px;">
        <p style="margin: 0 0 8px 0; font-weight: 600; color: #991b1b;">Error Details:</p>
        <p style="margin: 0 0 4px 0; color: #4a4a4a;"><strong>Level:</strong> {{ strtoupper($errorLog->level) }}</p>
        <p style="margin: 0 0 4px 0; color: #4a4a4a;"><strong>Type:</strong> {{ $errorLog->type }}</p>
        <p style="margin: 0 0 4px 0; color: #4a4a4a;"><strong>Message:</strong> {{ $errorLog->message }}</p>
        @if($errorLog->exception)
        <p style="margin: 0 0 4px 0; color: #4a4a4a;"><strong>Exception:</strong> {{ $errorLog->exception }}</p>
        @endif
        @if($errorLog->file)
        <p style="margin: 0 0 4px 0; color: #4a4a4a;"><strong>File:</strong> {{ $errorLog->file }}:{{ $errorLog->line }}</p>
        @endif
        @if($errorLog->url)
        <p style="margin: 0 0 4px 0; color: #4a4a4a;"><strong>URL:</strong> {{ $errorLog->url }}</p>
        @endif
        @if($errorLog->method)
        <p style="margin: 0 0 4px 0; color: #4a4a4a;"><strong>Method:</strong> {{ $errorLog->method }}</p>
        @endif
        @if($errorLog->user)
        <p style="margin: 0 0 4px 0; color: #4a4a4a;"><strong>User:</strong> {{ $errorLog->user->name }} ({{ $errorLog->user->email }})</p>
        @endif
        @if($errorLog->ip_address)
        <p style="margin: 0; color: #4a4a4a;"><strong>IP Address:</strong> {{ $errorLog->ip_address }}</p>
        @endif
    </div>

    @if($errorLog->trace)
    <div style="background-color: #f9fafb; border: 1px solid #e5e7eb; padding: 16px; margin: 24px 0; border-radius: 4px;">
        <p style="margin: 0 0 8px 0; font-weight: 600; color: #374151;">Stack Trace:</p>
        <pre style="margin: 0; font-size: 12px; color: #6b7280; white-space: pre-wrap; word-wrap: break-word;">{{ strlen($errorLog->trace) > 2000 ? substr($errorLog->trace, 0, 2000) . '...' : $errorLog->trace }}</pre>
    </div>
    @endif

    @if($errorLog->context && !empty($errorLog->context))
    <div style="background-color: #f9fafb; border: 1px solid #e5e7eb; padding: 16px; margin: 24px 0; border-radius: 4px;">
        <p style="margin: 0 0 8px 0; font-weight: 600; color: #374151;">Additional Context:</p>
        <pre style="margin: 0; font-size: 12px; color: #6b7280; white-space: pre-wrap; word-wrap: break-word;">{{ json_encode($errorLog->context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
    </div>
    @endif
    
    <p style="color: #6b7280; font-size: 14px; margin-top: 24px;">
        <strong>Error ID:</strong> #{{ $errorLog->id }}<br>
        <strong>Time:</strong> {{ $errorLog->created_at->format('Y-m-d H:i:s T') }}
    </p>
@endsection
