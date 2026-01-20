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
     */
    public function deleted(PayrollSchedule $payrollSchedule): void
    {
        // Cascade delete: Remove pivot table entries (no FK constraint)
        DB::table('payroll_schedule_employee')
            ->where('payroll_schedule_id', $payrollSchedule->id)
            ->delete();

        $this->auditService->log(
            'payroll_schedule.deleted',
            $payrollSchedule,
            $payrollSchedule->getAttributes()
        );
    }
}
