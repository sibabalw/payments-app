@extends('emails.layout')

@section('content')
    <h1>New contact form submission</h1>

    <p><strong>From:</strong> {{ $senderName }} &lt;{{ $senderEmail }}&gt;</p>

    <h2>Message</h2>
    <p style="white-space: pre-wrap;">{{ $message }}</p>

    <p class="text-muted" style="margin-top: 24px; font-size: 14px;">
        Reply directly to this email to respond to {{ $senderName }}.
    </p>
@endsection
