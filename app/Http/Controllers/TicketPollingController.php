<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\TicketMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TicketPollingController extends Controller
{
    /**
     * Poll for ticket updates (fallback when WebSockets are not available)
     */
    public function poll(Request $request)
    {
        $user = Auth::user();
        $since = $request->get('since', time() - 60); // Default to last minute
        $ticketId = $request->get('ticket_id');

        $events = [];

        // Get ticket updates since the last check
        $ticketQuery = Ticket::where('updated_at', '>', date('Y-m-d H:i:s', $since));

        // Filter by ticket if specified
        if ($ticketId) {
            $ticketQuery->where('id', $ticketId);
            
            // Verify user has access
            if (! $user->is_admin) {
                $ticketQuery->where('user_id', $user->id);
            }
        } else {
            // For ticket lists, filter by user if not admin
            if (! $user->is_admin) {
                $ticketQuery->where('user_id', $user->id);
            }
        }

        $updatedTickets = $ticketQuery->get();

        foreach ($updatedTickets as $ticket) {
            $events[] = [
                'channel' => $ticketId ? 'ticket.'.$ticket->id : 'tickets',
                'data' => [
                    'type' => 'ticket_updated',
                    'ticket_id' => $ticket->id,
                    'status' => $ticket->status,
                    'timestamp' => $ticket->updated_at->toIso8601String(),
                ],
            ];
        }

        // Get new messages since the last check
        $messageQuery = TicketMessage::where('created_at', '>', date('Y-m-d H:i:s', $since));

        if ($ticketId) {
            $messageQuery->where('ticket_id', $ticketId);
        }

        // Filter by user access
        if (! $user->is_admin) {
            $messageQuery->whereHas('ticket', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            });
        }

        $newMessages = $messageQuery->with('ticket')->get();

        foreach ($newMessages as $message) {
            $events[] = [
                'channel' => $ticketId ? 'ticket.'.$message->ticket_id : 'tickets',
                'data' => [
                    'type' => 'ticket_message_created',
                    'ticket_id' => $message->ticket_id,
                    'message_id' => $message->id,
                    'timestamp' => $message->created_at->toIso8601String(),
                ],
            ];
        }

        return response()->json([
            'events' => $events,
            'timestamp' => time(),
        ]);
    }
}
