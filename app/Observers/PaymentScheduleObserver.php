<?php

namespace App\Observers;

use App\Models\PaymentSchedule;
use App\Services\AuditService;

class PaymentScheduleObserver
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * Handle the PaymentSchedule "created" event.
     */
    public function created(PaymentSchedule $paymentSchedule): void
    {
        $this->auditService->log(
            'payment_schedule.created',
            $paymentSchedule,
            $paymentSchedule->getAttributes()
        );
    }

    /**
     * Handle the PaymentSchedule "updated" event.
     */
    public function updated(PaymentSchedule $paymentSchedule): void
    {
        $this->auditService->log(
            'payment_schedule.updated',
            $paymentSchedule,
            [
                'old' => $paymentSchedule->getOriginal(),
                'new' => $paymentSchedule->getChanges(),
            ]
        );
    }

    /**
     * Handle the PaymentSchedule "deleted" event.
     */
    public function deleted(PaymentSchedule $paymentSchedule): void
    {
        // Cascade delete: Remove pivot table entries (no FK constraint)
        \Illuminate\Support\Facades\DB::table('payment_schedule_recipient')
            ->where('payment_schedule_id', $paymentSchedule->id)
            ->delete();

        $this->auditService->log(
            'payment_schedule.deleted',
            $paymentSchedule,
            $paymentSchedule->getAttributes()
        );
    }
}
