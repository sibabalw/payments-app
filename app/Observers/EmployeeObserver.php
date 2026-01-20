<?php

namespace App\Observers;

use App\Models\Employee;
use Illuminate\Support\Facades\DB;

class EmployeeObserver
{
    /**
     * Handle the Employee "deleted" event.
     */
    public function deleted(Employee $employee): void
    {
        // Cascade delete: Remove pivot table entries (no FK constraint)
        DB::table('payroll_schedule_employee')
            ->where('employee_id', $employee->id)
            ->delete();
    }
}
