<?php

namespace App\Listeners;

use App\Services\MetricsService;

class FinancialOperationListener
{
    protected MetricsService $metricsService;

    public function __construct(MetricsService $metricsService)
    {
        $this->metricsService = $metricsService;
    }

    /**
     * Handle payment processed event
     */
    public function handlePaymentProcessed($event): void
    {
        $this->metricsService->recordTransaction('payment', true);
    }

    /**
     * Handle payment failed event
     */
    public function handlePaymentFailed($event): void
    {
        $this->metricsService->recordTransaction('payment', false, 'payment_failed');
    }

    /**
     * Handle payroll processed event
     */
    public function handlePayrollProcessed($event): void
    {
        $this->metricsService->recordTransaction('payroll', true);
    }

    /**
     * Handle payroll failed event
     */
    public function handlePayrollFailed($event): void
    {
        $this->metricsService->recordTransaction('payroll', false, 'payroll_failed');
    }
}
