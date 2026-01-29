<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReplyTicketRequest;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Events\TicketMessageCreated;
use App\Events\TicketUpdated;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AdminTicketController extends Controller
{

    /**
     * Display a listing of all tickets.
     */
    public function index(Request $request): Response
    {
        $status = $request->get('status');
        $priority = $request->get('priority');

        $query = Ticket::query()
            ->with(['user:id,name,email', 'assignedTo:id,name,email'])
            ->withCount('messages')
            ->orderByDesc('created_at');

        if ($status && in_array($status, ['open', 'in_progress', 'closed'])) {
            $query->where('status', $status);
        }

        if ($priority && in_array($priority, ['low', 'medium', 'high'])) {
            $query->where('priority', $priority);
        }

        $tickets = $query->paginate(20);

        return Inertia::render('admin/tickets/index', [
            'tickets' => $tickets,
            'filters' => [
                'status' => $status,
                'priority' => $priority,
            ],
        ]);
    }

    /**
     * Display the specified ticket.
     */
    public function show(Ticket $ticket): Response
    {
        $ticket->load([
            'user:id,name,email',
            'assignedTo:id,name,email',
            'messages.user:id,name,email',
        ]);

        // Get all admin users for assignment
        $users = \App\Models\User::where('is_admin', true)
            ->select('id', 'name', 'email')
            ->orderBy('name')
            ->get();

        return Inertia::render('admin/tickets/show', [
            'ticket' => $ticket,
            'users' => $users,
        ]);
    }

    /**
     * Add an admin reply to a ticket.
     */
    public function reply(ReplyTicketRequest $request, Ticket $ticket): RedirectResponse
    {
        if ($ticket->isClosed()) {
            return back()->withErrors(['message' => 'Cannot reply to a closed ticket.']);
        }

        $message = TicketMessage::create([
            'ticket_id' => $ticket->id,
            'user_id' => $request->user()->id,
            'message' => $request->message,
            'is_admin' => true,
        ]);

        // Update ticket status to in_progress if it was open
        if ($ticket->status === 'open') {
            $ticket->update(['status' => 'in_progress']);
        }

        // CRITICAL: Broadcast event after commit
        DB::afterCommit(function () use ($ticket, $message) {
            broadcast(new TicketMessageCreated($message, $ticket))->toOthers();
        });

        return back()->with('success', 'Reply added successfully.');
    }

    /**
     * Update ticket status.
     */
    public function updateStatus(Request $request, Ticket $ticket): RedirectResponse
    {
        $request->validate([
            'status' => ['required', 'in:open,in_progress,closed'],
        ]);

        $ticket->update([
            'status' => $request->status,
            'closed_at' => $request->status === 'closed' ? now() : null,
        ]);

        // CRITICAL: Broadcast event after commit
        DB::afterCommit(function () use ($ticket) {
            broadcast(new TicketUpdated($ticket))->toOthers();
        });

        return back()->with('success', 'Ticket status updated successfully.');
    }

    /**
     * Assign ticket to an admin.
     */
    public function assign(Request $request, Ticket $ticket): RedirectResponse
    {
        $request->validate([
            'assigned_to' => ['nullable', 'exists:users,id'],
        ]);

        $ticket->update([
            'assigned_to' => $request->assigned_to,
        ]);

        return back()->with('success', 'Ticket assigned successfully.');
    }
}
