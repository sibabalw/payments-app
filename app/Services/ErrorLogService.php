<?php

namespace App\Services;

use App\Mail\ErrorNotificationEmail;
use App\Models\ErrorLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class ErrorLogService
{
    public function __construct(
        protected EmailService $emailService
    ) {}

    /**
     * Log an error to the database and notify admins.
     */
    public function logError(
        Throwable $exception,
        ?Request $request = null,
        ?User $user = null,
        array $context = []
    ): ErrorLog {
        $isAdminError = $user?->is_admin ?? false;

        $errorLog = ErrorLog::create([
            'user_id' => $user?->id,
            'type' => 'exception',
            'level' => $this->determineLevel($exception),
            'message' => $exception->getMessage(),
            'exception' => get_class($exception),
            'trace' => $exception->getTraceAsString(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'url' => $request?->fullUrl(),
            'method' => $request?->method(),
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'context' => array_merge($context, [
                'route' => $request?->route()?->getName(),
                'request_data' => $this->sanitizeRequestData($request),
            ]),
            'is_admin_error' => $isAdminError,
            'notified' => false,
        ]);

        // Notify admins asynchronously
        $this->notifyAdmins($errorLog);

        return $errorLog;
    }

    /**
     * Log a simple error message.
     */
    public function logMessage(
        string $message,
        string $level = 'error',
        ?Request $request = null,
        ?User $user = null,
        array $context = []
    ): ErrorLog {
        $isAdminError = $user?->is_admin ?? false;

        $errorLog = ErrorLog::create([
            'user_id' => $user?->id,
            'type' => 'error',
            'level' => $level,
            'message' => $message,
            'url' => $request?->fullUrl(),
            'method' => $request?->method(),
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'context' => $context,
            'is_admin_error' => $isAdminError,
            'notified' => false,
        ]);

        // Notify admins for critical errors
        if (in_array($level, ['error', 'critical'])) {
            $this->notifyAdmins($errorLog);
        }

        return $errorLog;
    }

    /**
     * Notify all admin users about an error.
     */
    protected function notifyAdmins(ErrorLog $errorLog): void
    {
        try {
            // Get all admin users with verified emails
            $admins = User::query()
                ->where('is_admin', true)
                ->whereNotNull('email_verified_at')
                ->get();

            if ($admins->isEmpty()) {
                Log::warning('No admin users found to notify about error', [
                    'error_log_id' => $errorLog->id,
                ]);

                return;
            }

            // Send email to each admin
            $notifiedCount = 0;
            foreach ($admins as $admin) {
                try {
                    $this->emailService->send(
                        $admin,
                        new ErrorNotificationEmail($errorLog),
                        'error_notification'
                    );
                    $notifiedCount++;
                } catch (\Exception $e) {
                    Log::error('Failed to send error notification email to admin', [
                        'admin_id' => $admin->id,
                        'error_log_id' => $errorLog->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Update error log if at least one admin was notified
            if ($notifiedCount > 0) {
                $errorLog->update([
                    'notified' => true,
                    'notified_at' => now(),
                ]);
            }
        } catch (\Exception $e) {
            // Log but don't throw - we don't want error notification failures to break the app
            Log::error('Failed to notify admins about error', [
                'error_log_id' => $errorLog->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Determine error level from exception.
     */
    protected function determineLevel(Throwable $exception): string
    {
        // Check for specific exception types
        if ($exception instanceof \Symfony\Component\HttpKernel\Exception\HttpException) {
            $statusCode = $exception->getStatusCode();
            if ($statusCode >= 500) {
                return 'critical';
            }
            if ($statusCode >= 400) {
                return 'error';
            }
        }

        // Check for database connection issues
        if ($exception instanceof \Illuminate\Database\QueryException) {
            return 'critical';
        }

        // Default to error
        return 'error';
    }

    /**
     * Sanitize request data to remove sensitive information.
     */
    protected function sanitizeRequestData(?Request $request): ?array
    {
        if (! $request) {
            return null;
        }

        $data = $request->all();

        // Remove sensitive fields
        $sensitiveFields = ['password', 'password_confirmation', 'token', 'api_key', 'secret', 'credit_card', 'cvv'];
        foreach ($sensitiveFields as $field) {
            if (isset($data[$field])) {
                $data[$field] = '[REDACTED]';
            }
        }

        return $data;
    }
}
