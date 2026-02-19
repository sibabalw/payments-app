<?php

namespace App\Http\Controllers;

use App\Http\Requests\Employee\SendOtpRequest;
use App\Http\Requests\Employee\VerifyOtpRequest;
use App\Mail\EmployeeOtpEmail;
use App\Models\Employee;
use App\Models\TimeEntry;
use App\Services\AuditService;
use App\Services\EmployeeOtpService;
use App\Services\OvertimeCalculationService;
use App\Services\SouthAfricaHolidayService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeSignInController extends Controller
{
    public function __construct(
        protected EmployeeOtpService $otpService,
        protected AuditService $auditService,
        protected OvertimeCalculationService $overtimeService,
        protected SouthAfricaHolidayService $holidayService
    ) {}

    /**
     * Display the employee sign-in page (email entry).
     */
    public function show(Request $request): Response|RedirectResponse
    {
        // If already verified, redirect to time tracking
        if ($request->session()->has('employee_verified_id')) {
            $verifiedAt = $request->session()->get('employee_verified_at');
            $expiresAt = $verifiedAt + (24 * 60 * 60);

            if (now()->timestamp <= $expiresAt) {
                return redirect()->route('employee.time-tracking');
            }
        }

        return Inertia::render('employee/sign-in', [
            'status' => $request->session()->get('status'),
            'error' => $request->session()->get('error'),
            'otpSent' => $request->session()->get('otp_sent', false),
            'email' => $request->session()->get('otp_email'),
        ]);
    }

    /**
     * Send OTP to employee email.
     */
    public function sendOtp(SendOtpRequest $request): RedirectResponse
    {
        $email = strtolower($request->validated()['email']);
        $employee = Employee::where('email', $email)->firstOrFail();

        // Generate OTP (rate limiting is handled inside the service)
        // Only rate limit after successful validation
        try {
            $otp = $this->otpService->generateOtp($email);

            // Send OTP email
            Mail::to($employee->email)->send(new EmployeeOtpEmail($employee, $otp));

            return redirect()->route('employee.sign-in')
                ->with('otp_sent', true)
                ->with('otp_email', $email)
                ->with('status', 'OTP code has been sent to your email address.');
        } catch (\Exception $e) {
            // Check if it's a rate limit error
            if (str_contains($e->getMessage(), 'Too many') || str_contains($e->getMessage(), 'rate limit')) {
                return back()->withErrors([
                    'email' => 'Too many OTP requests. Please wait a few minutes before requesting another code.',
                ])->withInput();
            }
            throw $e;
        }
    }

    /**
     * Verify OTP and create session.
     */
    public function verifyOtp(VerifyOtpRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $email = strtolower($validated['email']);
        $otp = $validated['otp'];

        // Verify OTP
        if (! $this->otpService->verifyOtp($email, $otp)) {
            return redirect()->route('employee.sign-in')
                ->with('otp_sent', true)
                ->with('otp_email', $email)
                ->withErrors([
                    'otp' => 'Invalid or expired OTP code. Please request a new one.',
                ])
                ->withInput();
        }

        // Get employee
        $employee = Employee::where('email', $email)->firstOrFail();

        // Create verified session (24 hours)
        $request->session()->put('employee_verified_id', $employee->id);
        $request->session()->put('employee_verified_email', $employee->email);
        $request->session()->put('employee_verified_at', now()->timestamp);

        return redirect()->route('employee.time-tracking')
            ->with('success', 'Successfully verified! You can now sign in/out for time tracking.');
    }

    /**
     * Display time tracking page.
     */
    public function index(Request $request): Response
    {
        $employee = $request->get('employee'); // Set by middleware

        $today = today();
        $todayEntry = TimeEntry::where('employee_id', $employee->id)
            ->where('date', $today->format('Y-m-d'))
            ->whereNull('sign_out_time')
            ->first();

        $isSignedIn = $todayEntry && $todayEntry->isSignedIn();
        $signInTime = $todayEntry?->sign_in_time;
        $hoursWorked = 0;

        if ($signInTime) {
            $hoursWorked = Carbon::parse($signInTime)->diffInHours(now(), true);
        }

        return Inertia::render('employee/time-tracking', [
            'employee' => [
                'id' => $employee->id,
                'name' => $employee->name,
                'email' => $employee->email,
            ],
            'isSignedIn' => $isSignedIn,
            'signInTime' => $signInTime?->format('H:i:s'),
            'hoursWorked' => round($hoursWorked, 2),
            'today' => $today->format('Y-m-d'),
            'status' => $request->session()->get('success') ?? $request->session()->get('status'),
            'error' => $request->session()->get('error'),
        ]);
    }

    /**
     * Sign employee in for time tracking.
     */
    public function signIn(Request $request): RedirectResponse
    {
        $employee = $request->get('employee'); // Set by middleware
        $today = today();

        // Check if there's any entry for today (unique constraint: one entry per employee per day)
        $existingEntry = TimeEntry::where('employee_id', $employee->id)
            ->where('date', $today->format('Y-m-d'))
            ->first();

        if ($existingEntry) {
            // If already signed in (no sign_out_time), return error
            if ($existingEntry->sign_out_time === null) {
                return redirect()->route('employee.time-tracking')
                    ->with('error', 'You are already signed in.');
            }

            // If already signed out today, they can't sign in again (one entry per day)
            return redirect()->route('employee.time-tracking')
                ->with('error', 'You have already completed your time entry for today.');
        }

        DB::transaction(function () use ($employee, $today) {
            $entry = TimeEntry::create([
                'employee_id' => $employee->id,
                'business_id' => $employee->business_id,
                'date' => $today,
                'sign_in_time' => now(),
                'entry_type' => 'digital',
                'created_by' => null, // Employee self-sign-in, no user ID
            ]);

            $this->auditService->log('time_entry.signed_in', $entry, $entry->getAttributes());
        });

        return redirect()->route('employee.time-tracking')
            ->with('success', 'You have been signed in successfully.');
    }

    /**
     * Sign employee out for time tracking.
     */
    public function signOut(Request $request): RedirectResponse
    {
        $employee = $request->get('employee'); // Set by middleware
        $today = today();

        $entry = TimeEntry::where('employee_id', $employee->id)
            ->where('date', $today->format('Y-m-d'))
            ->whereNull('sign_out_time')
            ->first();

        if (! $entry) {
            return redirect()->route('employee.time-tracking')
                ->with('error', 'You are not signed in.');
        }

        DB::transaction(function () use ($entry, $employee, $today) {
            $entry->sign_out_time = now();

            // Calculate hours
            $totalHours = $entry->calculateHoursFromTimes();

            // Determine if weekend or holiday
            $isWeekend = $this->holidayService->isWeekend($today);
            $isHoliday = $this->holidayService->isHoliday($today);

            // Get scheduled hours
            $scheduledHours = $this->overtimeService->getScheduledHours($employee, $today);

            // Calculate regular vs overtime
            if ($scheduledHours > 0) {
                $regularHours = min($totalHours, $scheduledHours);
                $overtimeHours = max(0, $totalHours - $scheduledHours);
            } else {
                // No schedule set, all hours are regular
                $regularHours = $totalHours;
                $overtimeHours = 0;
            }

            // Assign hours based on day type
            if ($isHoliday) {
                $entry->holiday_hours = $totalHours;
                $entry->regular_hours = 0;
                $entry->overtime_hours = 0;
            } elseif ($isWeekend) {
                $entry->weekend_hours = $totalHours;
                $entry->regular_hours = 0;
                $entry->overtime_hours = 0;
            } else {
                $entry->regular_hours = $regularHours;
                $entry->overtime_hours = $overtimeHours;
            }

            $entry->save();

            $this->auditService->log('time_entry.signed_out', $entry, $entry->getAttributes());
        });

        return redirect()->route('employee.time-tracking')
            ->with('success', 'You have been signed out successfully.');
    }

    /**
     * Clear employee session and redirect to sign-in.
     */
    public function logout(Request $request): RedirectResponse
    {
        $request->session()->forget([
            'employee_verified_id',
            'employee_verified_at',
            'employee_verified_email',
        ]);

        return redirect()->route('employee.sign-in')
            ->with('status', 'You have been signed out successfully.');
    }
}
