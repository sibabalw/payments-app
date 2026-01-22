<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DashboardTourController extends Controller
{
    /**
     * Mark the dashboard tour as completed for the authenticated user.
     */
    public function complete(Request $request): RedirectResponse
    {
        $user = $request->user();

        $user->update([
            'has_completed_dashboard_tour' => true,
            'dashboard_tour_completed_at' => now(),
        ]);

        return back();
    }
}
