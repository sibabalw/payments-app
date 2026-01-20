<?php

namespace App\Http\Controllers;

use App\Mail\BusinessCreatedEmail;
use App\Mail\BusinessEmailOtpEmail;
use App\Mail\BusinessStatusChangedEmail;
use App\Models\Business;
use App\Services\AuditService;
use App\Services\BusinessEmailOtpService;
use App\Services\EmailService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class BusinessController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected AuditService $auditService,
        protected BusinessEmailOtpService $otpService
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(): Response
    {
        $user = Auth::user();

        // Get all businesses user has access to (owned + associated)
        $owned = $user->ownedBusinesses()->with('owner')->get();
        $associated = $user->businesses()->with('owner')->get();
        $businesses = $owned->merge($associated)->unique('id')->values();

        // Add important statistics to each business
        $businessesWithStats = $businesses->map(function ($business) {
            $logoUrl = null;
            if ($business->logo) {
                $logoUrl = \Illuminate\Support\Facades\Storage::disk('public')->url($business->logo);
            }

            return [
                'id' => $business->id,
                'name' => $business->name,
                'logo' => $logoUrl,
                'status' => $business->status,
                'status_reason' => $business->status_reason,
                'business_type' => $business->business_type,
                'email' => $business->email,
                'phone' => $business->phone,
                'escrow_balance' => (float) $business->escrow_balance,
                'employees_count' => \App\Models\Employee::where('business_id', $business->id)->count(),
                'payment_schedules_count' => $business->paymentSchedules()->count(),
                'payroll_schedules_count' => \App\Models\PayrollSchedule::where('business_id', $business->id)->count(),
                'recipients_count' => \App\Models\Recipient::where('business_id', $business->id)->count(),
                'created_at' => $business->created_at?->format('Y-m-d'),
                'status_changed_at' => $business->status_changed_at?->format('Y-m-d H:i'),
            ];
        });

        return Inertia::render('businesses/index', [
            'businesses' => $businessesWithStats,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        return Inertia::render('businesses/create');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Business $business): Response
    {
        $this->authorize('update', $business);

        $logoUrl = null;
        if ($business->logo) {
            $logoUrl = Storage::disk('public')->url($business->logo);
        }

        // Check if there's a pending email change
        $pendingEmail = session('business_email_change_'.$business->id);
        $otpSent = session('business_email_otp_sent_'.$business->id, false);
        $emailVerified = session('business_email_verified_'.$business->id, false);
        $otpExpiresAt = session('business_email_otp_expires_'.$business->id);

        // If OTP has expired, clear the session data
        if ($otpSent && $otpExpiresAt && now()->isAfter($otpExpiresAt)) {
            session()->forget([
                'business_email_change_'.$business->id,
                'business_email_otp_sent_'.$business->id,
                'business_email_otp_expires_'.$business->id,
                'business_email_verified_'.$business->id,
            ]);
            $pendingEmail = null;
            $otpSent = false;
            $emailVerified = false;
        }

        return Inertia::render('businesses/edit', [
            'business' => [
                'id' => $business->id,
                'name' => $business->name,
                'logo' => $logoUrl,
                'business_type' => $business->business_type,
                'registration_number' => $business->registration_number,
                'tax_id' => $business->tax_id,
                'email' => $business->email,
                'phone' => $business->phone,
                'website' => $business->website,
                'street_address' => $business->street_address,
                'city' => $business->city,
                'province' => $business->province,
                'postal_code' => $business->postal_code,
                'country' => $business->country,
                'description' => $business->description,
                'contact_person_name' => $business->contact_person_name,
            ],
            'pendingEmail' => $pendingEmail,
            'otpSent' => $otpSent,
            'emailVerified' => $emailVerified,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'business_type' => 'nullable|in:small_business,medium_business,large_business,sole_proprietorship,partnership,corporation,other',
            'registration_number' => 'nullable|string|max:255',
            'tax_id' => 'nullable|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:255',
            'website' => 'nullable|url|max:255',
            'street_address' => 'nullable|string|max:255',
            'city' => 'required|string|max:255',
            'province' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:255',
            'country' => 'required|string|max:255',
            'description' => 'nullable|string',
            'contact_person_name' => 'required|string|max:255',
        ]);

        // Handle logo upload first (outside transaction)
        $logoPath = null;
        if ($request->hasFile('logo')) {
            $logo = $request->file('logo');
            $logoPath = $logo->store('businesses/logos', 'public');
        }

        // Remove logo from validated array since we handle it separately
        unset($validated['logo']);

        try {
            // Wrap all database operations in a transaction
            DB::transaction(function () use ($logoPath, $validated, &$business, &$user) {
                $user = Auth::user();

                // Create business
                $business = Business::create([
                    'user_id' => $user->id,
                    'logo' => $logoPath,
                    ...$validated,
                ]);

                // Add user as owner in pivot table
                $business->users()->attach($user->id, ['role' => 'owner']);

                // If this is user's first business or they have no current business, set it as current
                if (! $user->current_business_id) {
                    $user->update(['current_business_id' => $business->id]);
                }

                // Log audit trail
                $this->auditService->log('business.created', $business, $business->getAttributes());
            });

            // If we get here, transaction succeeded
            // Send business created email (non-critical, happens after transaction)
            $emailService = app(EmailService::class);
            $emailService->send($user, new BusinessCreatedEmail($user, $business), 'business_created');

            return redirect()->route('businesses.index')
                ->with('success', 'Business created successfully.');

        } catch (\Exception $e) {
            // If transaction failed, clean up uploaded file
            if ($logoPath && Storage::disk('public')->exists($logoPath)) {
                Storage::disk('public')->delete($logoPath);
            }

            // Re-throw the exception to show error to user
            throw $e;
        }
    }

    /**
     * Send OTP for email verification.
     */
    public function sendEmailOtp(Request $request, Business $business)
    {
        $this->authorize('update', $business);

        $validated = $request->validate([
            'email' => 'required|email|max:255|unique:businesses,email,'.$business->id,
        ]);

        $newEmail = strtolower(trim($validated['email']));

        // Check if email is actually different
        if ($newEmail === strtolower($business->email)) {
            return back()->withErrors(['email' => 'This is already your current email address.']);
        }

        try {
            // Generate OTP
            $otp = $this->otpService->generateOtp($newEmail, $business->id);

            // Send OTP email
            Mail::to($newEmail)->queue(new BusinessEmailOtpEmail($business, $newEmail, $otp));

            // Store pending email in session with expiration time
            session([
                'business_email_change_'.$business->id => $newEmail,
                'business_email_otp_sent_'.$business->id => true,
                'business_email_otp_expires_'.$business->id => now()->addMinutes(10),
            ]);

            return back()->with('status', 'OTP code has been sent to your new email address. Please verify to complete the email change.');
        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), 'Too many') || str_contains($e->getMessage(), 'rate limit')) {
                return back()->withErrors([
                    'email' => 'Too many OTP requests. Please wait a few minutes before requesting another code.',
                ])->withInput();
            }
            throw $e;
        }
    }

    /**
     * Verify OTP for email change.
     */
    public function verifyEmailOtp(Request $request, Business $business)
    {
        $this->authorize('update', $business);

        $validated = $request->validate([
            'email' => 'required|email|max:255',
            'otp' => 'required|string|size:6|regex:/^[0-9]{6}$/',
        ]);

        $newEmail = strtolower(trim($validated['email']));
        $otp = $validated['otp'];

        // Verify OTP
        if (! $this->otpService->verifyOtp($newEmail, $otp, $business->id)) {
            return back()->withErrors([
                'otp' => 'Invalid or expired OTP code. Please request a new one.',
            ])->withInput();
        }

        // Verify the email matches the pending email in session
        $pendingEmail = session('business_email_change_'.$business->id);
        if ($pendingEmail !== $newEmail) {
            return back()->withErrors([
                'email' => 'Email does not match the pending email change. Please request a new OTP.',
            ])->withInput();
        }

        // Store the old email for audit
        $oldEmail = $business->email;

        // Immediately update the business email
        $business->update(['email' => $newEmail]);

        // Log the email change
        $this->auditService->log(
            'business.email_changed',
            $business,
            [
                'old_email' => $oldEmail,
                'new_email' => $newEmail,
            ]
        );

        // Clear all email change related session data
        session()->forget([
            'business_email_change_'.$business->id,
            'business_email_otp_sent_'.$business->id,
            'business_email_otp_expires_'.$business->id,
            'business_email_verified_'.$business->id,
        ]);

        return back()->with('status', 'Email updated successfully to '.$newEmail);
    }

    /**
     * Cancel pending email OTP verification.
     */
    public function cancelEmailOtp(Business $business)
    {
        $this->authorize('update', $business);

        // Clear all email change related session data
        session()->forget([
            'business_email_change_'.$business->id,
            'business_email_otp_sent_'.$business->id,
            'business_email_otp_expires_'.$business->id,
            'business_email_verified_'.$business->id,
        ]);

        return back()->with('status', 'Email change cancelled. You can enter a new email address.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Business $business)
    {
        $this->authorize('update', $business);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'business_type' => 'nullable|in:small_business,medium_business,large_business,sole_proprietorship,partnership,corporation,other',
            'registration_number' => 'nullable|string|max:255',
            'tax_id' => 'nullable|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:255',
            'website' => 'nullable|url|max:255',
            'street_address' => 'nullable|string|max:255',
            'city' => 'required|string|max:255',
            'province' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:255',
            'country' => 'required|string|max:255',
            'description' => 'nullable|string',
            'contact_person_name' => 'required|string|max:255',
        ]);

        $newEmail = strtolower(trim($validated['email']));
        $oldEmail = strtolower(trim($business->email));

        // If email is changing, require OTP verification
        if ($newEmail !== $oldEmail) {
            $pendingEmail = session('business_email_change_'.$business->id);
            $emailVerified = session('business_email_verified_'.$business->id, false);

            if ($pendingEmail !== $newEmail || ! $emailVerified) {
                return back()->withErrors([
                    'email' => 'Please verify your new email address with OTP before saving changes.',
                ])->withInput();
            }
        }

        // Store old logo path for cleanup
        $oldLogoPath = $business->logo;
        $newLogoPath = null;

        // Handle new logo upload first (outside transaction)
        if ($request->hasFile('logo')) {
            $logo = $request->file('logo');
            $newLogoPath = $logo->store('businesses/logos', 'public');
            $validated['logo'] = $newLogoPath;
        }

        try {
            // Wrap all database operations in a transaction
            DB::transaction(function () use ($validated, $business) {
                $business->update($validated);

                $this->auditService->log('business.updated', $business, [
                    'old' => $business->getOriginal(),
                    'new' => $business->getChanges(),
                ]);
            });

            // Clear email verification session data
            session()->forget([
                'business_email_change_'.$business->id,
                'business_email_otp_sent_'.$business->id,
                'business_email_verified_'.$business->id,
            ]);

            // If transaction succeeded, delete old logo (if new one was uploaded)
            if ($newLogoPath && $oldLogoPath && Storage::disk('public')->exists($oldLogoPath)) {
                Storage::disk('public')->delete($oldLogoPath);
            }

            return redirect()->route('businesses.index')
                ->with('success', 'Business updated successfully.');

        } catch (\Exception $e) {
            // If transaction failed, clean up newly uploaded file (keep old one)
            if ($newLogoPath && Storage::disk('public')->exists($newLogoPath)) {
                Storage::disk('public')->delete($newLogoPath);
            }

            // Re-throw the exception to show error to user
            throw $e;
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Business $business)
    {
        $this->auditService->log('business.deleted', $business, $business->getAttributes());

        $business->delete();

        return redirect()->route('businesses.index')
            ->with('success', 'Business deleted successfully.');
    }

    /**
     * Switch to a different business (store in database).
     */
    public function switch(Business $business)
    {
        $user = Auth::user();

        // Verify user has access to this business
        $hasAccess = $user->ownedBusinesses()->where('businesses.id', $business->id)->exists()
            || $user->businesses()->where('businesses.id', $business->id)->exists();

        if (! $hasAccess) {
            return redirect()->back()
                ->with('error', 'You do not have access to this business.');
        }

        // Prevent switching to banned or suspended businesses
        if (! $business->canPerformActions()) {
            return redirect()->back()
                ->with('error', "Cannot switch to this business. Status: {$business->status}.");
        }

        // Save to database
        $user->update(['current_business_id' => $business->id]);

        // Also update session for backward compatibility
        session(['current_business_id' => $business->id]);

        return redirect()->back()
            ->with('success', "Switched to {$business->name}.");
    }

    /**
     * Update business status (admin only)
     */
    public function updateStatus(Request $request, Business $business)
    {
        $validated = $request->validate([
            'status' => 'required|in:active,suspended,banned',
            'status_reason' => 'nullable|string|max:1000',
        ]);

        $oldStatus = $business->status;
        $business->updateStatus($validated['status'], $validated['status_reason'] ?? null);

        $this->auditService->log('business.status_updated', $business, [
            'old_status' => $oldStatus,
            'new_status' => $validated['status'],
            'reason' => $validated['status_reason'] ?? null,
        ]);

        // Send business status changed email to owner
        $user = $business->owner;
        $emailService = app(EmailService::class);
        $emailService->send(
            $user,
            new BusinessStatusChangedEmail(
                $user,
                $business,
                $oldStatus,
                $validated['status'],
                $validated['status_reason'] ?? null
            ),
            'business_status_changed'
        );

        return redirect()->back()
            ->with('success', "Business status updated to {$validated['status']}.");
    }

    /**
     * Show the form for editing bank account details.
     */
    public function editBankAccount(Business $business): Response
    {
        $this->authorize('update', $business);

        return Inertia::render('businesses/bank-account', [
            'business' => [
                'id' => $business->id,
                'name' => $business->name,
                'bank_account_details' => $business->bank_account_details,
            ],
        ]);
    }

    /**
     * Update the business bank account details.
     */
    public function updateBankAccount(\App\Http\Requests\UpdateBusinessBankAccountRequest $request, Business $business)
    {
        $validated = $request->validated();

        DB::transaction(function () use ($validated, $business) {
            $business->update([
                'bank_account_details' => $validated['bank_account_details'],
            ]);

            $this->auditService->log('business.bank_account_updated', $business, [
                'old' => $business->getOriginal('bank_account_details'),
                'new' => $validated['bank_account_details'],
            ]);
        });

        return redirect()->route('businesses.bank-account.edit', $business)
            ->with('success', 'Bank account details updated successfully.');
    }
}
