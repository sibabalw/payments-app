<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\EmployeeSchedule;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeScheduleController extends Controller
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * Show employee schedule editor
     */
    public function show(Employee $employee): Response
    {
        $schedules = EmployeeSchedule::where('employee_id', $employee->id)
            ->orderBy('day_of_week')
            ->get();

        // Ensure all 7 days exist (create if missing)
        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $schedule = $schedules->firstWhere('day_of_week', $i);
            $days[] = [
                'day_of_week' => $i,
                'day_name' => $this->getDayName($i),
                'scheduled_hours' => $schedule ? (float) $schedule->scheduled_hours : 0,
                'is_active' => $schedule ? $schedule->is_active : false,
                'id' => $schedule?->id,
            ];
        }

        return Inertia::render('employees/schedule', [
            'employee' => $employee->load('business'),
            'schedules' => $days,
        ]);
    }

    /**
     * Update employee schedule
     */
    public function update(Request $request, Employee $employee)
    {
        $validated = $request->validate([
            'schedules' => 'required|array|size:7',
            'schedules.*.day_of_week' => 'required|integer|min:0|max:6',
            'schedules.*.scheduled_hours' => 'required|numeric|min:0|max:24',
            'schedules.*.is_active' => 'boolean',
        ]);

        DB::transaction(function () use ($validated, $employee) {
            foreach ($validated['schedules'] as $scheduleData) {
                $schedule = EmployeeSchedule::updateOrCreate(
                    [
                        'employee_id' => $employee->id,
                        'day_of_week' => $scheduleData['day_of_week'],
                    ],
                    [
                        'scheduled_hours' => $scheduleData['scheduled_hours'],
                        'is_active' => $scheduleData['is_active'] ?? false,
                    ]
                );
            }

            $this->auditService->log('employee_schedule.updated', $employee, [
                'schedules' => $validated['schedules'],
            ]);
        });

        return redirect()->route('employees.schedule', $employee)
            ->with('success', 'Schedule updated successfully.');
    }

    /**
     * Get day name from day of week number
     */
    private function getDayName(int $dayOfWeek): string
    {
        $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

        return $days[$dayOfWeek] ?? 'Unknown';
    }
}
