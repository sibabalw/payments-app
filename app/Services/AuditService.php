<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Business;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Request;

class AuditService
{
    /**
     * Log an action to the audit log.
     */
    public function log(
        string $action,
        ?Model $model = null,
        ?array $changes = null,
        ?User $user = null,
        ?Business $business = null
    ): AuditLog {
        $user = $user ?? auth()->user();
        $business = $business ?? $this->getBusinessFromModel($model) ?? $this->getBusinessFromRequest();

        return AuditLog::create([
            'user_id' => $user?->id,
            'business_id' => $business?->id,
            'action' => $action,
            'model_type' => $model ? get_class($model) : null,
            'model_id' => $model?->id,
            'changes' => $changes,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
        ]);
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
            ?? session('current_business_id')
            ?? Request::input('business_id');

        if ($businessId) {
            return Business::find($businessId);
        }

        return null;
    }
}
