<?php

namespace App\Observers;

use App\Models\PaymentJob;
use App\Services\AuditService;

class PaymentJobObserver
{
    public function __construct(
        protected AuditService $auditService
    ) {
    }

    /**
     * Handle the PaymentJob "created" event.
     */
    public function created(PaymentJob $paymentJob): void
    {
        $this->auditService->log(
            'payment_job.created',
            $paymentJob,
            $paymentJob->getAttributes()
        );
    }

    /**
     * Handle the PaymentJob "updated" event.
     */
    public function updated(PaymentJob $paymentJob): void
    {
        // Only log status changes to avoid too many audit entries
        if ($paymentJob->wasChanged('status')) {
            $this->auditService->log(
                'payment_job.status_changed',
                $paymentJob,
                [
                    'old_status' => $paymentJob->getOriginal('status'),
                    'new_status' => $paymentJob->status,
                ]
            );
        }
    }
}
