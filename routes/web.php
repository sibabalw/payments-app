<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('public/home');
})->name('home');

Route::get('/features', function () {
    return Inertia::render('public/features');
})->name('features');

Route::get('/pricing', function () {
    return Inertia::render('public/pricing');
})->name('pricing');

Route::get('/about', function () {
    return Inertia::render('public/about');
})->name('about');

Route::get('/contact', function () {
    return Inertia::render('public/contact');
})->name('contact');

Route::get('/privacy', function () {
    return Inertia::render('public/privacy');
})->name('privacy');

Route::get('/terms', function () {
    return Inertia::render('public/terms');
})->name('terms');

// Google OAuth routes
Route::get('/auth/google', [\App\Http\Controllers\Auth\GoogleAuthController::class, 'redirect'])->name('google.redirect');
Route::get('/auth/google/callback', [\App\Http\Controllers\Auth\GoogleAuthController::class, 'callback'])->name('google.callback');

// Email verification route (override Fortify's default)
Route::middleware(['auth', 'signed', 'throttle:6,1'])->group(function () {
    Route::get('/email/verify/{id}/{hash}', \App\Http\Controllers\Auth\EmailVerificationController::class)
        ->name('verification.verify');
});

// Employee sign-in routes (public, no auth required)
Route::prefix('employee')->name('employee.')->group(function () {
    Route::get('/sign-in', [\App\Http\Controllers\EmployeeSignInController::class, 'show'])->name('sign-in');
    Route::post('/send-otp', [\App\Http\Controllers\EmployeeSignInController::class, 'sendOtp'])
        ->middleware('throttle:10,15')
        ->name('send-otp');
    Route::post('/verify-otp', [\App\Http\Controllers\EmployeeSignInController::class, 'verifyOtp'])
        ->middleware('throttle:20,15')
        ->name('verify-otp');
});

// Employee time tracking routes (protected by OTP verification)
Route::prefix('employee')->name('employee.')->middleware(\App\Http\Middleware\VerifyEmployeeOtp::class)->group(function () {
    Route::get('/time-tracking', [\App\Http\Controllers\EmployeeSignInController::class, 'index'])->name('time-tracking');
    Route::post('/time-tracking/sign-in', [\App\Http\Controllers\EmployeeSignInController::class, 'signIn'])->name('time-tracking.sign-in');
    Route::post('/time-tracking/sign-out', [\App\Http\Controllers\EmployeeSignInController::class, 'signOut'])->name('time-tracking.sign-out');
    Route::post('/sign-out-session', [\App\Http\Controllers\EmployeeSignInController::class, 'logout'])->name('sign-out-session');
});

// Admin OTP challenge (no auth required; requires valid pending session from password login)
Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/otp', [\App\Http\Controllers\Auth\AdminOtpController::class, 'show'])
        ->middleware('throttle:60,1')
        ->name('otp.show');
    Route::post('/otp', [\App\Http\Controllers\Auth\AdminOtpController::class, 'verify'])
        ->middleware('throttle:10,1')
        ->name('otp.verify');
    Route::post('/otp/resend', [\App\Http\Controllers\Auth\AdminOtpController::class, 'resend'])
        ->middleware('throttle:5,5')
        ->name('otp.resend');
});

Route::middleware(['auth', 'verified'])->group(function () {
    // Admin routes (protected by admin middleware) - must be first to take precedence
    Route::prefix('admin')->name('admin.')->middleware('admin')->group(function () {
        // Admin Dashboard
        Route::get('/', [\App\Http\Controllers\Admin\DashboardController::class, 'index'])->name('dashboard');

        // Business Management
        Route::get('/businesses', [\App\Http\Controllers\Admin\BusinessController::class, 'index'])->name('businesses.index');
        Route::post('/businesses/{business}/status', [\App\Http\Controllers\Admin\BusinessController::class, 'updateStatus'])->name('businesses.status');

        // Escrow Management
        Route::prefix('escrow')->name('escrow.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\EscrowController::class, 'index'])->name('index');
            Route::post('/deposits', [\App\Http\Controllers\Admin\EscrowController::class, 'createDeposit'])->name('deposits.create');
            Route::post('/deposits/{deposit}/confirm', [\App\Http\Controllers\Admin\EscrowController::class, 'confirmDeposit'])->name('deposits.confirm');
            Route::post('/payments/{paymentJob}/fee-release', [\App\Http\Controllers\Admin\EscrowController::class, 'recordFeeRelease'])->name('payments.fee-release');
            Route::post('/payments/{paymentJob}/fund-return', [\App\Http\Controllers\Admin\EscrowController::class, 'recordFundReturn'])->name('payments.fund-return');
            Route::get('/balances', [\App\Http\Controllers\Admin\EscrowController::class, 'viewBalances'])->name('balances');
        });

        // User Management
        Route::get('/users', [\App\Http\Controllers\Admin\UsersController::class, 'index'])->name('users.index');
        Route::get('/users/create', [\App\Http\Controllers\Admin\UsersController::class, 'create'])->name('users.create');
        Route::post('/users', [\App\Http\Controllers\Admin\UsersController::class, 'store'])->name('users.store');
        Route::post('/users/{user}/toggle-admin', [\App\Http\Controllers\Admin\UsersController::class, 'toggleAdmin'])->name('users.toggle-admin');

        // Audit Logs
        Route::get('/audit-logs', [\App\Http\Controllers\Admin\AuditLogsController::class, 'index'])->name('audit-logs.index');

        // Error Logs
        Route::get('/error-logs', [\App\Http\Controllers\Admin\ErrorLogsController::class, 'index'])->name('error-logs.index');
        Route::get('/error-logs/{errorLog}', [\App\Http\Controllers\Admin\ErrorLogsController::class, 'show'])->name('error-logs.show');

        // Admin Settings (system/application config)
        Route::get('/settings', [\App\Http\Controllers\Admin\SettingsController::class, 'index'])->name('settings.index');
        Route::post('/settings', [\App\Http\Controllers\Admin\SettingsController::class, 'update'])->name('settings.update');
        Route::post('/settings/clear-cache', [\App\Http\Controllers\Admin\SettingsController::class, 'clearCache'])->name('settings.clear-cache');

        // Admin Account (profile, password, appearance, two-factor - admin's own settings)
        Route::redirect('/account', '/admin/account/profile')->name('account.redirect');
        Route::get('/account/profile', [\App\Http\Controllers\Settings\ProfileController::class, 'edit'])->name('account.profile.edit');
        Route::patch('/account/profile', [\App\Http\Controllers\Settings\ProfileController::class, 'update'])->name('account.profile.update');
        Route::post('/account/profile/send-email-otp', [\App\Http\Controllers\Settings\ProfileController::class, 'sendEmailOtp'])->name('account.profile.send-email-otp');
        Route::post('/account/profile/verify-email-otp', [\App\Http\Controllers\Settings\ProfileController::class, 'verifyEmailOtp'])->name('account.profile.verify-email-otp');
        Route::post('/account/profile/cancel-email-otp', [\App\Http\Controllers\Settings\ProfileController::class, 'cancelEmailOtp'])->name('account.profile.cancel-email-otp');
        Route::delete('/account/profile', [\App\Http\Controllers\Settings\ProfileController::class, 'destroy'])->name('account.profile.destroy');
        Route::get('/account/password', [\App\Http\Controllers\Settings\PasswordController::class, 'edit'])->name('account.password.edit');
        Route::put('/account/password', [\App\Http\Controllers\Settings\PasswordController::class, 'update'])->name('account.password.update');
        Route::get('/account/appearance', function () {
            return \Inertia\Inertia::render('admin/account/appearance');
        })->name('account.appearance.edit');
        Route::get('/account/two-factor', [\App\Http\Controllers\Settings\TwoFactorAuthenticationController::class, 'show'])->name('account.two-factor.show');

        // System Health & Monitoring
        Route::get('/system-health', [\App\Http\Controllers\Admin\SystemHealthController::class, 'index'])->name('system-health.index');

        // System Configuration
        Route::get('/system-configuration', [\App\Http\Controllers\Admin\SystemConfigurationController::class, 'index'])->name('system-configuration.index');
        Route::post('/system-configuration/maintenance', [\App\Http\Controllers\Admin\SystemConfigurationController::class, 'toggleMaintenance'])->name('system-configuration.maintenance');

        // Logs Management
        Route::get('/logs', [\App\Http\Controllers\Admin\LogsController::class, 'index'])->name('logs.index');
        Route::post('/logs/clear', [\App\Http\Controllers\Admin\LogsController::class, 'clear'])->name('logs.clear');

        // Queue Management
        Route::get('/queue', [\App\Http\Controllers\Admin\QueueController::class, 'index'])->name('queue.index');
        Route::post('/queue/restart', [\App\Http\Controllers\Admin\QueueController::class, 'restart'])->name('queue.restart');
        Route::post('/queue/clear-failed', [\App\Http\Controllers\Admin\QueueController::class, 'clearFailed'])->name('queue.clear-failed');

        // Database Management
        Route::get('/database', [\App\Http\Controllers\Admin\DatabaseController::class, 'index'])->name('database.index');
        Route::post('/database/migrate', [\App\Http\Controllers\Admin\DatabaseController::class, 'migrate'])->name('database.migrate');
        Route::post('/database/optimize', [\App\Http\Controllers\Admin\DatabaseController::class, 'optimize'])->name('database.optimize');

        // Email Configuration
        Route::get('/email-configuration', [\App\Http\Controllers\Admin\EmailConfigurationController::class, 'index'])->name('email-configuration.index');
        Route::post('/email-configuration/test', [\App\Http\Controllers\Admin\EmailConfigurationController::class, 'test'])->name('email-configuration.test');

        // Subscription Management
        Route::get('/subscriptions', [\App\Http\Controllers\Admin\SubscriptionController::class, 'index'])->name('subscriptions.index');
        Route::post('/subscriptions/{billing}/status', [\App\Http\Controllers\Admin\SubscriptionController::class, 'updateStatus'])->name('subscriptions.update-status');

        // Feature Flags
        Route::get('/feature-flags', [\App\Http\Controllers\Admin\FeatureFlagsController::class, 'index'])->name('feature-flags.index');
        Route::post('/feature-flags/toggle', [\App\Http\Controllers\Admin\FeatureFlagsController::class, 'toggle'])->name('feature-flags.toggle');

        // Security Management
        Route::get('/security', [\App\Http\Controllers\Admin\SecurityController::class, 'index'])->name('security.index');
        Route::post('/security/rate-limit', [\App\Http\Controllers\Admin\SecurityController::class, 'updateRateLimit'])->name('security.update-rate-limit');
        Route::post('/security/rate-limit/clear', [\App\Http\Controllers\Admin\SecurityController::class, 'clearRateLimit'])->name('security.clear-rate-limit');

        // Performance Monitoring
        Route::get('/performance', [\App\Http\Controllers\Admin\PerformanceController::class, 'index'])->name('performance.index');

        // Storage Management
        Route::get('/storage', [\App\Http\Controllers\Admin\StorageController::class, 'index'])->name('storage.index');
        Route::post('/storage/clear-cache', [\App\Http\Controllers\Admin\StorageController::class, 'clearCache'])->name('storage.clear-cache');
        Route::post('/storage/clear-sessions', [\App\Http\Controllers\Admin\StorageController::class, 'clearSessions'])->name('storage.clear-sessions');

        // System Reports
        Route::get('/system-reports', [\App\Http\Controllers\Admin\SystemReportsController::class, 'index'])->name('system-reports.index');
    });

    // Regular user routes (protected by user middleware - blocks admins)
    Route::middleware('user')->group(function () {
        // Onboarding routes
        Route::get('/onboarding', [\App\Http\Controllers\OnboardingController::class, 'index'])->name('onboarding.index');
        Route::post('/onboarding', [\App\Http\Controllers\OnboardingController::class, 'store'])->name('onboarding.store');
        Route::post('/onboarding/skip', [\App\Http\Controllers\OnboardingController::class, 'skip'])->name('onboarding.skip');

        // Dashboard tour completion
        Route::post('/dashboard/complete-tour', [\App\Http\Controllers\DashboardTourController::class, 'complete'])->name('dashboard.complete-tour');

        // Dashboard - optimized with proper SQL aggregations
        Route::get('dashboard', [\App\Http\Controllers\DashboardController::class, 'index'])->name('dashboard');

        // Business routes
        Route::resource('businesses', \App\Http\Controllers\BusinessController::class)->except(['show', 'create', 'edit']);
        Route::get('businesses/create', [\App\Http\Controllers\BusinessController::class, 'create'])->name('businesses.create');
        Route::get('businesses/{business}/edit', [\App\Http\Controllers\BusinessController::class, 'edit'])->name('businesses.edit');
        Route::post('businesses/{business}/send-email-otp', [\App\Http\Controllers\BusinessController::class, 'sendEmailOtp'])->name('businesses.send-email-otp');
        Route::post('businesses/{business}/verify-email-otp', [\App\Http\Controllers\BusinessController::class, 'verifyEmailOtp'])->name('businesses.verify-email-otp');
        Route::post('businesses/{business}/cancel-email-otp', [\App\Http\Controllers\BusinessController::class, 'cancelEmailOtp'])->name('businesses.cancel-email-otp');
        Route::post('businesses/{business}/switch', [\App\Http\Controllers\BusinessController::class, 'switch'])->name('businesses.switch');
        Route::post('businesses/{business}/status', [\App\Http\Controllers\BusinessController::class, 'updateStatus'])->name('businesses.status');
        Route::get('businesses/{business}/bank-account', [\App\Http\Controllers\BusinessController::class, 'editBankAccount'])->name('businesses.bank-account.edit');
        Route::put('businesses/{business}/bank-account', [\App\Http\Controllers\BusinessController::class, 'updateBankAccount'])->name('businesses.bank-account.update');

        // Recipient routes (for payments)
        Route::resource('recipients', \App\Http\Controllers\RecipientController::class);

        // Employee routes (for payroll)
        Route::resource('employees', \App\Http\Controllers\EmployeeController::class)->except(['show']);
        Route::get('employees/search', [\App\Http\Controllers\EmployeeController::class, 'search'])->name('employees.search');
        Route::post('employees/calculate-tax', [\App\Http\Controllers\EmployeeController::class, 'calculateTax'])->name('employees.calculate-tax');
        Route::post('employees/{employee}/calculate-tax', [\App\Http\Controllers\EmployeeController::class, 'calculateTax'])->name('employees.calculate-tax.existing');
        Route::get('employees/{employee}/schedule', [\App\Http\Controllers\EmployeeScheduleController::class, 'show'])->name('employees.schedule');
        Route::put('employees/{employee}/schedule', [\App\Http\Controllers\EmployeeScheduleController::class, 'update'])->name('employees.schedule.update');

        // Adjustment routes
        Route::get('adjustments/calculate-period', [\App\Http\Controllers\AdjustmentController::class, 'calculatePeriod'])->name('adjustments.calculate-period');
        Route::resource('adjustments', \App\Http\Controllers\AdjustmentController::class);
        Route::get('employees/{employee}/adjustments', [\App\Http\Controllers\AdjustmentController::class, 'employeeIndex'])->name('employees.adjustments.index');

        // Time tracking routes
        Route::get('time-tracking', [\App\Http\Controllers\TimeEntryController::class, 'index'])->name('time-tracking.index');
        Route::post('time-tracking/{employee}/sign-in', [\App\Http\Controllers\TimeEntryController::class, 'signIn'])->name('time-tracking.sign-in');
        Route::post('time-tracking/{employee}/sign-out', [\App\Http\Controllers\TimeEntryController::class, 'signOut'])->name('time-tracking.sign-out');
        Route::get('time-tracking/manual', [\App\Http\Controllers\TimeEntryController::class, 'manual'])->name('time-tracking.manual');
        Route::post('time-tracking/entries', [\App\Http\Controllers\TimeEntryController::class, 'store'])->name('time-tracking.entries.store');
        Route::put('time-tracking/entries/{timeEntry}', [\App\Http\Controllers\TimeEntryController::class, 'update'])->name('time-tracking.entries.update');
        Route::delete('time-tracking/entries/{timeEntry}', [\App\Http\Controllers\TimeEntryController::class, 'destroy'])->name('time-tracking.entries.destroy');
        Route::get('time-tracking/status', [\App\Http\Controllers\TimeEntryController::class, 'getTodayStatus'])->name('time-tracking.status');

        // Leave routes
        Route::resource('leave', \App\Http\Controllers\LeaveController::class);

        // Payment schedule routes
        // Payment job routes (must be before resource route to avoid conflict)
        Route::get('payments/jobs', [\App\Http\Controllers\PaymentJobController::class, 'index'])->name('payments.jobs');
        Route::get('payments/jobs/{paymentJob}', [\App\Http\Controllers\PaymentJobController::class, 'show'])->name('payments.jobs.show');

        Route::resource('payments', \App\Http\Controllers\PaymentScheduleController::class)->except(['edit', 'update']);
        Route::get('payments/{paymentSchedule}/edit', [\App\Http\Controllers\PaymentScheduleController::class, 'edit'])->name('payments.edit');
        Route::put('payments/{paymentSchedule}', [\App\Http\Controllers\PaymentScheduleController::class, 'update'])->name('payments.update');
        Route::post('payments/{paymentSchedule}/pause', [\App\Http\Controllers\PaymentScheduleController::class, 'pause'])->name('payments.pause');
        Route::post('payments/{paymentSchedule}/resume', [\App\Http\Controllers\PaymentScheduleController::class, 'resume'])->name('payments.resume');
        Route::post('payments/{paymentSchedule}/cancel', [\App\Http\Controllers\PaymentScheduleController::class, 'cancel'])->name('payments.cancel');

        // Payroll routes
        Route::get('payroll', [\App\Http\Controllers\PayrollController::class, 'index'])->name('payroll.index');
        Route::get('payroll/create', [\App\Http\Controllers\PayrollController::class, 'create'])->name('payroll.create');
        Route::post('payroll', [\App\Http\Controllers\PayrollController::class, 'store'])->name('payroll.store');
        Route::get('payroll/{payrollSchedule}/edit', [\App\Http\Controllers\PayrollController::class, 'edit'])->name('payroll.edit');
        Route::get('payroll/{payrollSchedule}/tax-breakdowns', [\App\Http\Controllers\PayrollController::class, 'getTaxBreakdowns'])->name('payroll.tax-breakdowns');
        Route::put('payroll/{payrollSchedule}', [\App\Http\Controllers\PayrollController::class, 'update'])->name('payroll.update');
        Route::delete('payroll/{payrollSchedule}', [\App\Http\Controllers\PayrollController::class, 'destroy'])->name('payroll.destroy');
        Route::post('payroll/{payrollSchedule}/pause', [\App\Http\Controllers\PayrollController::class, 'pause'])->name('payroll.pause');
        Route::post('payroll/{payrollSchedule}/resume', [\App\Http\Controllers\PayrollController::class, 'resume'])->name('payroll.resume');
        Route::post('payroll/{payrollSchedule}/cancel', [\App\Http\Controllers\PayrollController::class, 'cancel'])->name('payroll.cancel');
        Route::get('payroll/jobs', [\App\Http\Controllers\PayrollController::class, 'jobs'])->name('payroll.jobs');
        Route::get('payroll/jobs/{payrollJob}', [\App\Http\Controllers\PayrollJobController::class, 'show'])->name('payroll.jobs.show');

        // Payslip routes
        Route::get('payslips', [\App\Http\Controllers\PayslipController::class, 'index'])->name('payslips.index');
        Route::get('payslips/{payrollJob}', [\App\Http\Controllers\PayslipController::class, 'show'])->name('payslips.show');
        Route::get('payslips/{payrollJob}/pdf', [\App\Http\Controllers\PayslipController::class, 'pdf'])->name('payslips.pdf');
        Route::get('payslips/{payrollJob}/download', [\App\Http\Controllers\PayslipController::class, 'download'])->name('payslips.download');
        Route::get('employees/{employee}/payslips', [\App\Http\Controllers\PayslipController::class, 'employeePayslips'])->name('employees.payslips');

        // Reports routes
        Route::get('reports', [\App\Http\Controllers\ReportController::class, 'index'])->name('reports.index');
        Route::get('reports/data', [\App\Http\Controllers\ReportController::class, 'fetchReportData'])->name('reports.data');
        Route::get('reports/export/csv', [\App\Http\Controllers\ReportController::class, 'exportCsv'])->name('reports.export.csv');
        Route::get('reports/export/excel', [\App\Http\Controllers\ReportController::class, 'exportExcel'])->name('reports.export.excel');
        Route::get('reports/export/pdf', [\App\Http\Controllers\ReportController::class, 'exportPdf'])->name('reports.export.pdf');
        Route::get('reports/{reportGeneration}/download-wait', [\App\Http\Controllers\ReportController::class, 'downloadWait'])->name('reports.download-wait');
        Route::get('reports/{reportGeneration}/email-wait', [\App\Http\Controllers\ReportController::class, 'emailWait'])->name('reports.email-wait');
        Route::get('reports/{reportGeneration}/stream', [\App\Http\Controllers\ReportController::class, 'stream'])->name('reports.stream');
        Route::get('reports/{reportGeneration}/download', [\App\Http\Controllers\ReportController::class, 'download'])->name('reports.download');

        // Audit log routes
        Route::get('audit-logs', [\App\Http\Controllers\AuditLogController::class, 'index'])->name('audit-logs.index');
        Route::get('audit-logs/{auditLog}', [\App\Http\Controllers\AuditLogController::class, 'show'])->name('audit-logs.show');

        // Template routes
        Route::prefix('templates')->name('templates.')->group(function () {
            Route::get('/', [\App\Http\Controllers\TemplateController::class, 'index'])->name('index');
            Route::get('/{type}', [\App\Http\Controllers\TemplateController::class, 'show'])->name('show');
            Route::put('/{type}', [\App\Http\Controllers\TemplateController::class, 'update'])->name('update');
            Route::post('/{type}/reset', [\App\Http\Controllers\TemplateController::class, 'reset'])->name('reset');
            Route::get('/{type}/preview', [\App\Http\Controllers\TemplateController::class, 'preview'])->name('preview');
            Route::post('/{type}/preset', [\App\Http\Controllers\TemplateController::class, 'loadPreset'])->name('load-preset');
        });

        // Escrow deposit routes
        Route::get('escrow/deposit', [\App\Http\Controllers\EscrowDepositController::class, 'index'])->name('escrow.deposit.index');
        Route::post('escrow/deposit', [\App\Http\Controllers\EscrowDepositController::class, 'store'])->name('escrow.deposit.store');
        Route::get('escrow/deposit/{id}', [\App\Http\Controllers\EscrowDepositController::class, 'show'])->name('escrow.deposit.show');

        // Billing routes
        Route::get('billing', [\App\Http\Controllers\BillingController::class, 'index'])->name('billing.index');
        Route::get('billing/{id}', [\App\Http\Controllers\BillingController::class, 'show'])->name('billing.show');

        // Compliance routes
        Route::prefix('compliance')->name('compliance.')->group(function () {
            Route::get('/', [\App\Http\Controllers\ComplianceController::class, 'index'])->name('index');

            // UIF routes
            Route::get('/uif', [\App\Http\Controllers\ComplianceController::class, 'uifIndex'])->name('uif.index');
            Route::post('/uif/generate', [\App\Http\Controllers\ComplianceController::class, 'generateUI19'])->name('uif.generate');
            Route::get('/uif/{submission}/edit', [\App\Http\Controllers\ComplianceController::class, 'editUI19'])->name('uif.edit');
            Route::put('/uif/{submission}', [\App\Http\Controllers\ComplianceController::class, 'updateUI19'])->name('uif.update');
            Route::get('/uif/{submission}/download', [\App\Http\Controllers\ComplianceController::class, 'downloadUI19'])->name('uif.download');

            // EMP201 routes
            Route::get('/emp201', [\App\Http\Controllers\ComplianceController::class, 'emp201Index'])->name('emp201.index');
            Route::post('/emp201/generate', [\App\Http\Controllers\ComplianceController::class, 'generateEMP201'])->name('emp201.generate');
            Route::get('/emp201/{submission}/edit', [\App\Http\Controllers\ComplianceController::class, 'editEMP201'])->name('emp201.edit');
            Route::put('/emp201/{submission}', [\App\Http\Controllers\ComplianceController::class, 'updateEMP201'])->name('emp201.update');
            Route::get('/emp201/{submission}/download', [\App\Http\Controllers\ComplianceController::class, 'downloadEMP201'])->name('emp201.download');

            // IRP5 routes
            Route::get('/irp5', [\App\Http\Controllers\ComplianceController::class, 'irp5Index'])->name('irp5.index');
            Route::post('/irp5/generate/{employee}', [\App\Http\Controllers\ComplianceController::class, 'generateIRP5'])->name('irp5.generate');
            Route::post('/irp5/generate-bulk', [\App\Http\Controllers\ComplianceController::class, 'generateBulkIRP5'])->name('irp5.generate-bulk');
            Route::get('/irp5/{submission}/edit', [\App\Http\Controllers\ComplianceController::class, 'editIRP5'])->name('irp5.edit');
            Route::put('/irp5/{submission}', [\App\Http\Controllers\ComplianceController::class, 'updateIRP5'])->name('irp5.update');
            Route::get('/irp5/{submission}/download', [\App\Http\Controllers\ComplianceController::class, 'downloadIRP5'])->name('irp5.download');

            // SARS export
            Route::get('/sars-export', [\App\Http\Controllers\ComplianceController::class, 'sarsExport'])->name('sars.export');

            // Mark as submitted
            Route::post('/{submission}/mark-submitted', [\App\Http\Controllers\ComplianceController::class, 'markSubmitted'])->name('mark-submitted');
        });

        // AI Chat routes
        Route::prefix('chat')->name('chat.')->group(function () {
            Route::get('/', [\App\Http\Controllers\ChatController::class, 'index'])->name('index');
            Route::post('/', [\App\Http\Controllers\ChatController::class, 'store'])->name('store');
            Route::get('/{conversation}', [\App\Http\Controllers\ChatController::class, 'show'])->name('show');
            Route::post('/{conversation}/message', [\App\Http\Controllers\ChatController::class, 'sendMessage'])->name('message');
            Route::delete('/{conversation}', [\App\Http\Controllers\ChatController::class, 'destroy'])->name('destroy');
        });
    });
});

require __DIR__.'/settings.php';
