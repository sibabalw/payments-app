<?php

use App\Models\Ticket;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

Broadcast::channel('ticket.{ticketId}', function ($user, $ticketId) {
    $ticket = Ticket::find($ticketId);

    if (! $ticket) {
        return false;
    }

    // Admins can access all tickets
    if ($user->is_admin) {
        return true;
    }

    // Regular users can only access their own tickets
    return $ticket->user_id === $user->id;
});

Broadcast::channel('user.{userId}.tickets', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

Broadcast::channel('admin.tickets', function ($user) {
    return $user->is_admin ?? false;
});
