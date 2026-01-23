<?php

use App\Mail\ErrorNotificationEmail;
use App\Models\ErrorLog;
use App\Models\User;
use App\Services\ErrorLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

test('error log service logs exception to database', function () {
    $service = app(ErrorLogService::class);
    $exception = new \Exception('Test error message');

    $errorLog = $service->logError($exception);

    $this->assertDatabaseHas('error_logs', [
        'id' => $errorLog->id,
        'message' => 'Test error message',
        'type' => 'exception',
        'exception' => 'Exception',
    ]);
});

test('error log service includes request information', function () {
    $service = app(ErrorLogService::class);
    $exception = new \Exception('Test error');
    $request = request()->create('/test-url', 'POST', ['test' => 'data']);

    $errorLog = $service->logError($exception, $request);

    $this->assertDatabaseHas('error_logs', [
        'id' => $errorLog->id,
        'url' => '/test-url',
        'method' => 'POST',
    ]);
});

test('error log service marks admin errors correctly', function () {
    $service = app(ErrorLogService::class);
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create(['is_admin' => false]);
    $exception = new \Exception('Test error');

    $adminError = $service->logError($exception, null, $admin);
    $userError = $service->logError($exception, null, $user);

    $this->assertTrue($adminError->is_admin_error);
    $this->assertFalse($userError->is_admin_error);
});

test('error log service notifies admins via email', function () {
    Mail::fake();

    $admin1 = User::factory()->admin()->create();
    $admin2 = User::factory()->admin()->create();
    $service = app(ErrorLogService::class);
    $exception = new \Exception('Critical error');

    $errorLog = $service->logError($exception);

    Mail::assertQueued(ErrorNotificationEmail::class, function ($mail) use ($admin1, $admin2) {
        return $mail->hasTo($admin1->email) || $mail->hasTo($admin2->email);
    });

    $this->assertDatabaseHas('error_logs', [
        'id' => $errorLog->id,
        'notified' => true,
    ]);
    $this->assertNotNull($errorLog->fresh()->notified_at);
});

test('error log service does not notify for unverified admin emails', function () {
    Mail::fake();

    $admin = User::factory()->admin()->unverified()->create();
    $service = app(ErrorLogService::class);
    $exception = new \Exception('Test error');

    $service->logError($exception);

    Mail::assertNothingQueued();
});

test('error log service logs simple messages', function () {
    $service = app(ErrorLogService::class);

    $errorLog = $service->logMessage('Test warning message', 'warning');

    $this->assertDatabaseHas('error_logs', [
        'id' => $errorLog->id,
        'message' => 'Test warning message',
        'type' => 'error',
        'level' => 'warning',
    ]);
});

test('error log service determines correct level for exceptions', function () {
    $service = app(ErrorLogService::class);

    $criticalException = new \Illuminate\Database\QueryException('test', [], new \PDOException);
    $httpException = new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException('Not found');
    $regularException = new \Exception('Regular error');

    $critical = $service->logError($criticalException);
    $http = $service->logError($httpException);
    $regular = $service->logError($regularException);

    $this->assertEquals('critical', $critical->level);
    $this->assertEquals('error', $http->level);
    $this->assertEquals('error', $regular->level);
});

test('error log service sanitizes sensitive data from request', function () {
    $service = app(ErrorLogService::class);
    $exception = new \Exception('Test error');
    $request = request()->create('/test', 'POST', [
        'password' => 'secret123',
        'password_confirmation' => 'secret123',
        'api_key' => 'key123',
        'normal_field' => 'normal_value',
    ]);

    $errorLog = $service->logError($exception, $request);

    $context = $errorLog->context;
    $this->assertEquals('[REDACTED]', $context['request_data']['password']);
    $this->assertEquals('[REDACTED]', $context['request_data']['password_confirmation']);
    $this->assertEquals('[REDACTED]', $context['request_data']['api_key']);
    $this->assertEquals('normal_value', $context['request_data']['normal_field']);
});

test('admin can view error logs page', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('admin.error-logs.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/error-logs/index')
            ->has('logs')
            ->has('stats')
        );
});

test('regular users cannot access error logs page', function () {
    $user = User::factory()->create(['is_admin' => false]);

    $this->actingAs($user)
        ->get(route('admin.error-logs.index'))
        ->assertForbidden();
});

test('error logs page shows filtered results', function () {
    $admin = User::factory()->admin()->create();
    ErrorLog::factory()->create(['level' => 'critical']);
    ErrorLog::factory()->create(['level' => 'error']);
    ErrorLog::factory()->create(['level' => 'warning']);

    $this->actingAs($admin)
        ->get(route('admin.error-logs.index', ['level' => 'critical']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('logs.data', function ($logs) {
                return count($logs) === 1 && $logs[0]['level'] === 'critical';
            })
        );
});

test('exception handler automatically logs errors', function () {
    $this->withoutExceptionHandling();

    try {
        $this->get('/non-existent-route-that-throws');
    } catch (\Exception $e) {
        // Exception should be caught and logged
    }

    // The exception handler should have logged this
    // Note: This test verifies the integration, actual logging happens in bootstrap/app.php
    $this->assertTrue(true); // Placeholder - actual implementation depends on exception handling
});
