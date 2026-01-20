<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\TimeEntry;
use App\Services\AuditService;
use App\Services\OvertimeCalculationService;
use App\Services\SouthAfricaHolidayService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class TimeEntryController extends Controller
{
    public function __construct(
        protected AuditService $auditService,
        protected OvertimeCalculationService $overtimeService,
        protected SouthAfricaHolidayService $holidayService
    ) {}

    /**
     * Display time tracking dashboard
     */
    public function index(Request $request): Response
    {
        $user = Auth::user();
        $businessId = $request->get('business_id') ?? $user->current_business_id ?? session('current_business_id');

        if (! $businessId) {
            $businessId = $user->businesses()->first()?->id;
        }

        // Verify user has access to this business
        if ($businessId) {
            $hasAccess = $user->businesses()
                ->where('businesses.id', $businessId)
                ->exists()
                || $user->ownedBusinesses()
                    ->where('id', $businessId)
                    ->exists();

            if (! $hasAccess) {
                // Fallback to first accessible business
                $businessId = $user->businesses()->first()?->id ?? $user->ownedBusinesses()->first()?->id;
            }
        }

        $employees = Employee::where('business_id', $businessId)
            ->with(['timeEntries' => function ($query) {
                $query->where('date', today()->format('Y-m-d'))
                    ->whereNull('sign_out_time');
            }])
            ->get();

        $today = today();
        $employeesWithStatus = $employees->map(function ($employee) {
            $todayEntry = $employee->timeEntries->first();
            $isSignedIn = $todayEntry && $todayEntry->isSignedIn();

            return [
                'id' => $employee->id,
                'name' => $employee->name,
                'email' => $employee->email,
                'is_signed_in' => $isSignedIn,
                'sign_in_time' => $todayEntry?->sign_in_time,
                'today_hours' => $todayEntry && $todayEntry->sign_in_time
                    ? Carbon::parse($todayEntry->sign_in_time)->diffInHours(now(), true)
                    : 0,
            ];
        });

        $businesses = Auth::user()->businesses()->get();

        return Inertia::render('time-tracking/index', [
            'employees' => $employeesWithStatus,
            'businesses' => $businesses,
            'selectedBusinessId' => $businessId,
            'today' => $today->format('Y-m-d'),
        ]);
    }

    /**
     * Sign employee in
     */
    public function signIn(Employee $employee)
    {
        // Verify user has access to this employee's business
        $user = Auth::user();
        $hasAccess = $user->businesses()
            ->where('businesses.id', $employee->business_id)
            ->exists()
            || $user->ownedBusinesses()
                ->where('id', $employee->business_id)
                ->exists();

        if (! $hasAccess) {
            return back()->withErrors(['error' => 'You do not have access to sign in employees from this business.']);
        }

        $today = today();

        // Check if already signed in today
        $existingEntry = TimeEntry::where('employee_id', $employee->id)
            ->where('date', $today->format('Y-m-d'))
            ->whereNull('sign_out_time')
            ->first();

        if ($existingEntry) {
            return back()->withErrors(['error' => 'Employee is already signed in.']);
        }

        DB::transaction(function () use ($employee, $today) {
            $entry = TimeEntry::create([
                'employee_id' => $employee->id,
                'business_id' => $employee->business_id,
                'date' => $today,
                'sign_in_time' => now(),
                'entry_type' => 'digital',
                'created_by' => Auth::id(),
            ]);

            $this->auditService->log('time_entry.signed_in', $entry, $entry->getAttributes());
        });

        return back()->with('success', 'Employee signed in successfully.');
    }

    /**
     * Sign employee out
     */
    public function signOut(Employee $employee)
    {
        // Verify user has access to this employee's business
        $user = Auth::user();
        $hasAccess = $user->businesses()
            ->where('businesses.id', $employee->business_id)
            ->exists()
            || $user->ownedBusinesses()
                ->where('id', $employee->business_id)
                ->exists();

        if (! $hasAccess) {
            return back()->withErrors(['error' => 'You do not have access to sign out employees from this business.']);
        }

        $today = today();

        $entry = TimeEntry::where('employee_id', $employee->id)
            ->where('date', $today->format('Y-m-d'))
            ->whereNull('sign_out_time')
            ->first();

        if (! $entry) {
            return back()->withErrors(['error' => 'Employee is not signed in.']);
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

        return back()->with('success', 'Employee signed out successfully.');
    }

    /**
     * Show manual entry page
     */
    public function manual(Request $request): Response
    {
        $businessId = $request->get('business_id') ?? Auth::user()->current_business_id ?? session('current_business_id');

        if (! $businessId) {
            $businessId = Auth::user()->businesses()->first()?->id;
        }

        $employees = Employee::where('business_id', $businessId)->get();
        $businesses = Auth::user()->businesses()->get();

        $date = $request->get('date') ?? today()->format('Y-m-d');

        $entries = TimeEntry::where('business_id', $businessId)
            ->where('date', $date)
            ->where('entry_type', 'manual')
            ->with('employee')
            ->latest()
            ->get();

        return Inertia::render('time-tracking/manual', [
            'employees' => $employees,
            'businesses' => $businesses,
            'selectedBusinessId' => $businessId,
            'selectedDate' => $date,
            'entries' => $entries,
        ]);
    }

    /**
     * Store manual time entry
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'date' => 'required|date',
            'sign_in_time' => 'required|date',
            'sign_out_time' => 'required|date|after:sign_in_time',
            'bonus_amount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $employee = Employee::findOrFail($validated['employee_id']);

        // Check if entry already exists for this date
        $existing = TimeEntry::where('employee_id', $employee->id)
            ->where('date', $validated['date'])
            ->first();

        if ($existing) {
            return back()->withErrors(['error' => 'Time entry already exists for this date.']);
        }

        DB::transaction(function () use ($validated, $employee) {
            $date = Carbon::parse($validated['date']);
            $signIn = Carbon::parse($validated['sign_in_time']);
            $signOut = Carbon::parse($validated['sign_out_time']);

            $totalHours = round($signOut->diffInHours($signIn, true), 2);

            // Determine if weekend or holiday
            $isWeekend = $this->holidayService->isWeekend($date);
            $isHoliday = $this->holidayService->isHoliday($date);

            // Get scheduled hours
            $scheduledHours = $this->overtimeService->getScheduledHours($employee, $date);

            // Calculate regular vs overtime
            if ($scheduledHours > 0) {
                $regularHours = min($totalHours, $scheduledHours);
                $overtimeHours = max(0, $totalHours - $scheduledHours);
            } else {
                $regularHours = $totalHours;
                $overtimeHours = 0;
            }

            $entry = TimeEntry::create([
                'employee_id' => $employee->id,
                'business_id' => $employee->business_id,
                'date' => $validated['date'],
                'sign_in_time' => $validated['sign_in_time'],
                'sign_out_time' => $validated['sign_out_time'],
                'regular_hours' => $isHoliday || $isWeekend ? 0 : $regularHours,
                'overtime_hours' => $isHoliday || $isWeekend ? 0 : $overtimeHours,
                'weekend_hours' => $isWeekend ? $totalHours : 0,
                'holiday_hours' => $isHoliday ? $totalHours : 0,
                'bonus_amount' => $validated['bonus_amount'] ?? 0,
                'notes' => $validated['notes'] ?? null,
                'entry_type' => 'manual',
                'created_by' => Auth::id(),
            ]);

            $this->auditService->log('time_entry.created', $entry, $entry->getAttributes());
        });

        return back()->with('success', 'Time entry created successfully.');
    }

    /**
     * Update manual time entry
     */
    public function update(Request $request, TimeEntry $timeEntry)
    {
        $validated = $request->validate([
            'date' => 'required|date',
            'sign_in_time' => 'required|date',
            'sign_out_time' => 'required|date|after:sign_in_time',
            'bonus_amount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        // Only allow updating manual entries
        if ($timeEntry->entry_type !== 'manual') {
            return back()->withErrors(['error' => 'Only manual entries can be edited.']);
        }

        DB::transaction(function () use ($validated, $timeEntry) {
            $employee = $timeEntry->employee;
            $date = Carbon::parse($validated['date']);
            $signIn = Carbon::parse($validated['sign_in_time']);
            $signOut = Carbon::parse($validated['sign_out_time']);

            $totalHours = round($signOut->diffInHours($signIn, true), 2);

            // Determine if weekend or holiday
            $isWeekend = $this->holidayService->isWeekend($date);
            $isHoliday = $this->holidayService->isHoliday($date);

            // Get scheduled hours
            $scheduledHours = $this->overtimeService->getScheduledHours($employee, $date);

            // Calculate regular vs overtime
            if ($scheduledHours > 0) {
                $regularHours = min($totalHours, $scheduledHours);
                $overtimeHours = max(0, $totalHours - $scheduledHours);
            } else {
                $regularHours = $totalHours;
                $overtimeHours = 0;
            }

            $timeEntry->update([
                'date' => $validated['date'],
                'sign_in_time' => $validated['sign_in_time'],
                'sign_out_time' => $validated['sign_out_time'],
                'regular_hours' => $isHoliday || $isWeekend ? 0 : $regularHours,
                'overtime_hours' => $isHoliday || $isWeekend ? 0 : $overtimeHours,
                'weekend_hours' => $isWeekend ? $totalHours : 0,
                'holiday_hours' => $isHoliday ? $totalHours : 0,
                'bonus_amount' => $validated['bonus_amount'] ?? 0,
                'notes' => $validated['notes'] ?? null,
            ]);

            $this->auditService->log('time_entry.updated', $timeEntry, [
                'old' => $timeEntry->getOriginal(),
                'new' => $timeEntry->getChanges(),
            ]);
        });

        return back()->with('success', 'Time entry updated successfully.');
    }

    /**
     * Delete time entry
     */
    public function destroy(TimeEntry $timeEntry)
    {
        // Only allow deleting manual entries
        if ($timeEntry->entry_type !== 'manual') {
            return back()->withErrors(['error' => 'Only manual entries can be deleted.']);
        }

        $this->auditService->log('time_entry.deleted', $timeEntry, $timeEntry->getAttributes());

        $timeEntry->delete();

        return back()->with('success', 'Time entry deleted successfully.');
    }

    /**
     * Get today's status (API endpoint)
     */
    public function getTodayStatus(Request $request)
    {
        $businessId = $request->get('business_id') ?? Auth::user()->current_business_id;

        $employees = Employee::where('business_id', $businessId)->get();
        $today = today();

        $status = $employees->map(function ($employee) use ($today) {
            $entry = TimeEntry::where('employee_id', $employee->id)
                ->where('date', $today->format('Y-m-d'))
                ->whereNull('sign_out_time')
                ->first();

            return [
                'employee_id' => $employee->id,
                'is_signed_in' => $entry !== null,
                'sign_in_time' => $entry?->sign_in_time,
            ];
        });

        return response()->json($status);
    }
}
