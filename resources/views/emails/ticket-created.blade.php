@extends('emails.layout')

@section('content')
    <h1>New Support Ticket</h1>
    
    <p>A new support ticket has been created and requires your attention.</p>
    
    <div style="background-color: #f9fafb; border-left: 4px solid #1a1a1a; padding: 16px; margin: 24px 0; border-radius: 4px;">
        <p style="margin: 0 0 8px 0; font-weight: 600;">Ticket Details:</p>
        <p style="margin: 0 0 4px 0; color: #4a4a4a;"><strong>Subject:</strong> {{ $ticket->subject }}</p>
        <p style="margin: 0 0 4px 0; color: #4a4a4a;"><strong>Priority:</strong> {{ ucfirst($ticket->priority) }}</p>
        <p style="margin: 0 0 4px 0; color: #4a4a4a;"><strong>Status:</strong> {{ ucfirst(str_replace('_', ' ', $ticket->status)) }}</p>
        <p style="margin: 0 0 4px 0; color: #4a4a4a;"><strong>From:</strong> {{ $ticket->user->name }} ({{ $ticket->user->email }})</p>
        <p style="margin: 0; color: #4a4a4a;"><strong>Created:</strong> {{ $ticket->created_at->format('F d, Y \a\t g:i A') }}</p>
    </div>

    <div style="background-color: #ffffff; border: 1px solid #e5e7eb; padding: 16px; margin: 24px 0; border-radius: 4px;">
        <p style="margin: 0 0 8px 0; font-weight: 600;">Description:</p>
        <p style="margin: 0; color: #4a4a4a; white-space: pre-wrap;">{{ $ticket->description }}</p>
    </div>
    
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin: 24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" cellspacing="0" cellpadding="0" border="0">
                    <tr>
                        <td align="center" style="background-color: #1a1a1a; border-radius: 8px; padding: 0;">
                            <a href="{{ route('admin.tickets.show', $ticket->id) }}" style="display: inline-block; padding: 14px 32px; font-size: 16px; font-weight: 600; color: #ffffff; text-decoration: none; border-radius: 8px;">
                                View Ticket
                            </a>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
@endsection
