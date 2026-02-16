<?php

namespace App\Services;

use App\Jobs\LogAuditJob;
use App\Models\Business;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditService
{
    /**
     * Log an action to the audit log (queued).
     */
    public function log(
        string $action,
        ?Model $model = null,
        ?array $changes = null,
        ?User $user = null,
        ?Business $business = null,
        ?string $correlationId = null,
        ?array $beforeValues = null,
        ?array $afterValues = null,
        ?array $metadata = null
    ): void {
        $user = $user ?? auth()->user();
        $business = $business ?? $this->getBusinessFromModel($model) ?? $this->getBusinessFromRequest();

        // Queue the audit log write to avoid blocking the request
        LogAuditJob::dispatch(
            action: $action,
            userId: $user?->id,
            businessId: $business?->id,
            modelType: $model ? get_class($model) : null,
            modelId: $model?->id,
            changes: $changes,
            ipAddress: Request::ip(),
            userAgent: Request::userAgent(),
            correlationId: $correlationId,
            beforeValues: $beforeValues,
            afterValues: $afterValues,
            metadata: $metadata
        )->onQueue('audit');
    }

    /**
     * Log a financial operation with full context
     */
    public function logFinancialOperation(
        string $action,
        Model $model,
        ?array $beforeValues = null,
        ?array $afterValues = null,
        ?string $correlationId = null,
        ?User $user = null,
        ?Business $business = null,
        ?array $metadata = null
    ): void {
        $this->log(
            $action,
            $model,
            null, // changes will be derived from before/after
            $user,
            $business,
            $correlationId,
            $beforeValues,
            $afterValues,
            $metadata
        );
    }

    /**
     * Get business from model if it has a business relationship.
     */
    protected function getBusinessFromModel(?Model $model): ?Business
    {
        if (! $model) {
            return null;
        }

        // Check if model has business_id or business relationship
        if (isset($model->business_id)) {
            return Business::find($model->business_id);
        }

        if (method_exists($model, 'business')) {
            return $model->business;
        }

        return null;
    }

    /**
     * Get business from request (if stored in session or request).
     */
    protected function getBusinessFromRequest(): ?Business
    {
        $businessId = Request::header('X-Business-Id')
            ?? (Auth::check() ? Auth::user()->current_business_id : null)
            ?? session('current_business_id')
            ?? Request::input('business_id');

        if ($businessId) {
            return Business::find($businessId);
        }

        return null;
    }
}
