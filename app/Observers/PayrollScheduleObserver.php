<?php

namespace App\Observers;

use App\Models\PayrollSchedule;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;

class PayrollScheduleObserver
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * Handle the PayrollSchedule "deleted" event.
     * Note: Audit logging is handled in the controller before deletion
     * to ensure all related data is captured. This observer only handles
     * cascade deletion of pivot table entries.
     */
    public function deleted(PayrollSchedule $payrollSchedule): void
    {
        // Cascade delete: Remove pivot table entries (no FK constraint)
        DB::table('payroll_schedule_employee')
            ->where('payroll_schedule_id', $payrollSchedule->id)
            ->delete();

        // Audit logging is done in PayrollController::destroy() before deletion
        // to ensure comprehensive data capture including related employees
    }
}
