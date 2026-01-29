<?php

namespace App\Events;

use App\Models\Ticket;
use App\Models\TicketMessage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TicketMessageCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public TicketMessage $message,
        public Ticket $ticket
    ) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('tickets'), // Public channel for all ticket updates
            new PrivateChannel('ticket.'.$this->ticket->id), // Private channel for specific ticket
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'ticket.message.created';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'type' => 'ticket_message_created',
            'ticket_id' => $this->ticket->id,
            'message' => [
                'id' => $this->message->id,
                'message' => $this->message->message,
                'is_admin' => $this->message->is_admin,
                'created_at' => $this->message->created_at->toIso8601String(),
                'user' => [
                    'id' => $this->message->user_id,
                    'name' => $this->message->user->name ?? null,
                    'email' => $this->message->user->email ?? null,
                ],
            ],
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
