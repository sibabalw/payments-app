<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Services\EscrowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class EscrowDepositController extends Controller
{
    public function __construct(
        protected EscrowService $escrowService
    ) {
    }

    /**
     * Display the deposit management page.
     */
    public function index(): Response
    {
        $businessId = session('current_business_id');
        $user = Auth::user();

        $businesses = $user->businesses()->get();
        
        if (!$businessId && $businesses->isNotEmpty()) {
            $businessId = $businesses->first()->id;
        }

        $business = $businessId ? Business::find($businessId) : null;
        $availableBalance = $business ? $this->escrowService->getAvailableBalance($business) : 0;
        $escrowAccountNumber = config('escrow.account_number', '');

        $deposits = $business
            ? $business->escrowDeposits()->orderBy('created_at', 'desc')->get()
            : collect();

        return Inertia::render('escrow/deposit', [
            'businesses' => $businesses,
            'selectedBusinessId' => $businessId,
            'escrowAccountNumber' => $escrowAccountNumber,
            'availableBalance' => $availableBalance,
            'deposits' => $deposits,
        ]);
    }

    /**
     * Store a new deposit.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'business_id' => 'required|exists:businesses,id',
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'required|string|size:3',
        ]);

        $business = Business::findOrFail($validated['business_id']);

        // Verify user has access to this business
        if (!Auth::user()->businesses()->where('businesses.id', $business->id)->exists()) {
            return back()->withErrors(['business_id' => 'You do not have access to this business.']);
        }

        try {
            $deposit = $this->escrowService->createDeposit(
                $business,
                $validated['amount'],
                $validated['currency'],
                Auth::user()
            );

            return redirect()->route('escrow.deposit.index')
                ->with('success', 'Deposit recorded successfully. Status: Pending bank confirmation. Authorized amount: ' . number_format($deposit->authorized_amount, 2) . ' (after 1.5% fee)');
        } catch (\Exception $e) {
            return back()->withErrors(['amount' => $e->getMessage()])->withInput();
        }
    }

    /**
     * Show deposit details.
     */
    public function show($id): Response
    {
        $deposit = \App\Models\EscrowDeposit::with(['business', 'paymentJobs'])
            ->findOrFail($id);

        // Verify user has access
        if (!Auth::user()->businesses()->where('businesses.id', $deposit->business_id)->exists()) {
            abort(403);
        }

        return Inertia::render('escrow/deposit-show', [
            'deposit' => $deposit,
        ]);
    }
}
