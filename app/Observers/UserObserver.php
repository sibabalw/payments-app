<?php

namespace App\Observers;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class UserObserver
{
    /**
     * Handle the User "deleted" event.
     */
    public function deleted(User $user): void
    {
        // Cascade delete: Remove pivot table entries (no FK constraint)
        DB::table('business_user')
            ->where('user_id', $user->id)
            ->delete();
    }
}
