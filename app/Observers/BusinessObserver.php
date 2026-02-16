<?php

namespace App\Observers;

use App\Models\Business;
use Illuminate\Support\Facades\DB;

class BusinessObserver
{
    /**
     * Handle the Business "deleted" event.
     */
    public function deleted(Business $business): void
    {
        // Cascade delete: Remove pivot table entries (no FK constraint)
        DB::table('business_user')
            ->where('business_id', $business->id)
            ->delete();
    }
}
