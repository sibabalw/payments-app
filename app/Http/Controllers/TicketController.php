<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReplyTicketRequest;
use App\Http\Requests\StoreTicketRequest;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Events\TicketMessageCreated;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class TicketController extends Controller
{

    /**
     * Display a listing of the user's tickets.
     */
    public function index(Request $request): Response
    {
        $status = $request->get('status');

        $query = Ticket::query()
            ->where('user_id', Auth::id())
            ->with(['user:id,name,email'])
            ->orderByDesc('created_at');

        if ($status && in_array($status, ['open', 'in_progress', 'closed'])) {
            $query->where('status', $status);
        }

        $tickets = $query->paginate(15);

        return Inertia::render('tickets/index', [
            'tickets' => $tickets,
            'filters' => [
                'status' => $status,
            ],
        ]);
    }

    /**
     * Show the form for creating a new ticket.
     */
    public function create(): Response
    {
        return Inertia::render('tickets/create');
    }

    /**
     * Store a newly created ticket.
     */
    public function store(StoreTicketRequest $request): RedirectResponse
    {
        $ticket = Ticket::create([
            'user_id' => Auth::id(),
            'subject' => $request->subject,
            'description' => $request->description,
            'priority' => $request->priority ?? 'medium',
            'status' => 'open',
        ]);

        return to_route('tickets.show', $ticket->id)
            ->with('success', 'Ticket created successfully. An admin will respond soon.');
    }

    /**
     * Display the specified ticket.
     */
    public function show(Ticket $ticket): Response
    {
        // Ensure user can only view their own tickets
        if ($ticket->user_id !== Auth::id()) {
            abort(403);
        }

        $ticket->load(['user:id,name,email', 'messages.user:id,name,email']);

        return Inertia::render('tickets/show', [
            'ticket' => $ticket,
        ]);
    }

    /**
     * Add a reply to a ticket.
     */
    public function reply(ReplyTicketRequest $request, Ticket $ticket): RedirectResponse
    {
        // Ensure user can only reply to their own tickets
        if ($ticket->user_id !== Auth::id()) {
            abort(403);
        }

        // Don't allow replies to closed tickets
        if ($ticket->isClosed()) {
            return back()->withErrors(['message' => 'Cannot reply to a closed ticket.']);
        }

        $message = TicketMessage::create([
            'ticket_id' => $ticket->id,
            'user_id' => Auth::id(),
            'message' => $request->message,
            'is_admin' => false,
        ]);

        // Update ticket status if it was in progress
        if ($ticket->status === 'in_progress') {
            $ticket->update(['status' => 'open']);
        }

        // CRITICAL: Broadcast event after commit
        DB::afterCommit(function () use ($ticket, $message) {
            broadcast(new \App\Events\TicketMessageCreated($message, $ticket))->toOthers();
        });

        return back()->with('success', 'Reply added successfully.');
    }
}
