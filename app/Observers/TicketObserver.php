<?php

namespace App\Observers;

use App\Events\TicketCreated;
use App\Events\TicketsListUpdated;
use App\Events\TicketUpdated;
use App\Mail\TicketCreatedEmail;
use App\Models\Ticket;
use App\Models\User;
use App\Services\EmailService;
use Illuminate\Support\Facades\DB;

class TicketObserver
{
    public function __construct(
        protected EmailService $emailService
    ) {}

    /**
     * Handle the Ticket "created" event.
     *
     * CRITICAL: All notifications MUST be sent after commit to ensure:
     * 1. Database transaction is fully committed before notifying
     * 2. PostgreSQL LISTEN/NOTIFY only works after commit
     * 3. Email queue jobs are only queued after data is persisted
     *
     * Do NOT rely on observer timing - always use DB::afterCommit() explicitly.
     */
    public function created(Ticket $ticket): void
    {
        // CRITICAL: Explicitly wait for commit before sending notifications
        DB::afterCommit(function () use ($ticket) {
            // Send email to all admins (queued after commit)
            $admins = User::where('is_admin', true)->get();

            foreach ($admins as $admin) {
                $this->emailService->send(
                    $admin,
                    new TicketCreatedEmail($ticket, $admin),
                    'ticket_created'
                );
            }

            // Broadcast ticket created event via WebSocket
            broadcast(new TicketCreated($ticket))->toOthers();
        });
    }

    /**
     * Handle the Ticket "updated" event.
     *
     * CRITICAL: PostgreSQL notification MUST be sent after commit.
     * LISTEN/NOTIFY only works after the transaction commits.
     *
     * Do NOT rely on observer timing - always use DB::afterCommit() explicitly.
     */
    public function updated(Ticket $ticket): void
    {
        // CRITICAL: Explicitly wait for commit before broadcasting
        DB::afterCommit(function () use ($ticket) {
            broadcast(new TicketUpdated($ticket))->toOthers();
            broadcast(new TicketsListUpdated($ticket))->toOthers();
        });
    }
}
