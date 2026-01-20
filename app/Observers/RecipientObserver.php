<?php

namespace App\Observers;

use App\Models\Recipient;
use Illuminate\Support\Facades\DB;

class RecipientObserver
{
    /**
     * Handle the Recipient "deleted" event.
     */
    public function deleted(Recipient $recipient): void
    {
        // Cascade delete: Remove pivot table entries (no FK constraint)
        DB::table('payment_schedule_recipient')
            ->where('recipient_id', $recipient->id)
            ->delete();
    }
}
