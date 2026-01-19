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

Route::middleware(['auth', 'verified'])->group(function () {
    // Onboarding routes
    Route::get('/onboarding', [\App\Http\Controllers\OnboardingController::class, 'index'])->name('onboarding.index');
    Route::post('/onboarding', [\App\Http\Controllers\OnboardingController::class, 'store'])->name('onboarding.store');
    Route::post('/onboarding/skip', [\App\Http\Controllers\OnboardingController::class, 'skip'])->name('onboarding.skip');

    Route::get('dashboard', function () {
        $user = auth()->user();

        // If user hasn't completed onboarding, redirect to onboarding
        if (! $user->onboarding_completed_at) {
            // Check if user has businesses, if so mark onboarding as completed
            $hasBusinesses = $user->businesses()->count() > 0 || $user->ownedBusinesses()->count() > 0;

            if ($hasBusinesses) {
                $user->update(['onboarding_completed_at' => now()]);
            } else {
                return redirect()->route('onboarding.index');
            }
        }

        // Auto-select business if user has businesses but no current_business_id set
        if (! $user->current_business_id) {
            $firstBusiness = $user->ownedBusinesses()->first() ?? $user->businesses()->first();
            if ($firstBusiness) {
                $user->update(['current_business_id' => $firstBusiness->id]);
                $user->refresh();
            }
        }

        $businessId = $user->current_business_id ?? session('current_business_id'); // Fallback to session for compatibility

        $userBusinessIds = $user->businesses()->pluck('businesses.id');

        $query = \App\Models\PaymentSchedule::query();
        if ($businessId) {
            $query->where('business_id', $businessId);
        } else {
            $query->whereIn('business_id', $userBusinessIds);
        }

        $totalSchedules = $query->count();
        $activeSchedules = (clone $query)->where('status', 'active')->count();

        $jobQuery = \App\Models\PaymentJob::query()
            ->whereHas('paymentSchedule', function ($q) use ($businessId, $userBusinessIds) {
                if ($businessId) {
                    $q->where('business_id', $businessId);
                } else {
                    $q->whereIn('business_id', $userBusinessIds);
                }
            });

        $pendingJobs = (clone $jobQuery)->where('status', 'pending')->count();
        $processingJobs = (clone $jobQuery)->where('status', 'processing')->count();
        $succeededJobs = (clone $jobQuery)->where('status', 'succeeded')->count();
        $failedJobs = (clone $jobQuery)->where('status', 'failed')->count();

        $upcomingPayments = \App\Models\PaymentSchedule::query()
            ->whereIn('business_id', $businessId ? [$businessId] : $userBusinessIds)
            ->where('status', 'active')
            ->where('next_run_at', '>=', now())
            ->orderBy('next_run_at')
            ->limit(10)
            ->with(['business', 'receivers'])
            ->get();

        $recentJobs = \App\Models\PaymentJob::query()
            ->whereHas('paymentSchedule', function ($q) use ($businessId, $userBusinessIds) {
                if ($businessId) {
                    $q->where('business_id', $businessId);
                } else {
                    $q->whereIn('business_id', $userBusinessIds);
                }
            })
            ->with(['paymentSchedule', 'receiver'])
            ->latest()
            ->limit(10)
            ->get();

        // Get escrow balance for selected business (or first business if none selected)
        $escrowBalance = 0;
        $selectedBusiness = null;

        if ($businessId) {
            $selectedBusiness = \App\Models\Business::find($businessId);
            // Verify user has access
            if ($selectedBusiness && ! $user->businesses()->where('businesses.id', $selectedBusiness->id)->exists()) {
                $selectedBusiness = null;
            }
        }

        // If no business selected or found, try to get first business from relationships
        if (! $selectedBusiness) {
            $selectedBusiness = $user->businesses()->first();
        }

        // Also check owned businesses if still no business found
        if (! $selectedBusiness) {
            $selectedBusiness = $user->ownedBusinesses()->first();
        }

        if ($selectedBusiness) {
            $escrowService = app(\App\Services\EscrowService::class);
            $escrowBalance = $escrowService->getAvailableBalance($selectedBusiness);
        }

        // Calculate total businesses count (both relationships)
        $businessesCount = $user->businesses()->count() + $user->ownedBusinesses()->count();

        return Inertia::render('dashboard', [
            'metrics' => [
                'total_schedules' => $totalSchedules,
                'active_schedules' => $activeSchedules,
                'pending_jobs' => $pendingJobs,
                'processing_jobs' => $processingJobs,
                'succeeded_jobs' => $succeededJobs,
                'failed_jobs' => $failedJobs,
            ],
            'upcomingPayments' => $upcomingPayments,
            'recentJobs' => $recentJobs,
            'escrowBalance' => $escrowBalance,
            'selectedBusiness' => $selectedBusiness ? ['id' => $selectedBusiness->id, 'name' => $selectedBusiness->name] : null,
            'businessesCount' => $businessesCount,
        ]);
    })->name('dashboard');

    // Business routes
    Route::resource('businesses', \App\Http\Controllers\BusinessController::class)->except(['show', 'create', 'edit']);
    Route::get('businesses/create', [\App\Http\Controllers\BusinessController::class, 'create'])->name('businesses.create');
    Route::post('businesses/{business}/switch', [\App\Http\Controllers\BusinessController::class, 'switch'])->name('businesses.switch');
    Route::post('businesses/{business}/status', [\App\Http\Controllers\BusinessController::class, 'updateStatus'])->name('businesses.status');
    Route::get('businesses/{business}/bank-account', [\App\Http\Controllers\BusinessController::class, 'editBankAccount'])->name('businesses.bank-account.edit');
    Route::put('businesses/{business}/bank-account', [\App\Http\Controllers\BusinessController::class, 'updateBankAccount'])->name('businesses.bank-account.update');

    // Recipient routes (for payments)
    Route::resource('recipients', \App\Http\Controllers\RecipientController::class);

    // Employee routes (for payroll)
    Route::resource('employees', \App\Http\Controllers\EmployeeController::class);
    Route::post('employees/calculate-tax', [\App\Http\Controllers\EmployeeController::class, 'calculateTax'])->name('employees.calculate-tax');
    Route::post('employees/{employee}/calculate-tax', [\App\Http\Controllers\EmployeeController::class, 'calculateTax'])->name('employees.calculate-tax.existing');
    Route::get('employees/{employee}/schedule', [\App\Http\Controllers\EmployeeScheduleController::class, 'show'])->name('employees.schedule');
    Route::put('employees/{employee}/schedule', [\App\Http\Controllers\EmployeeScheduleController::class, 'update'])->name('employees.schedule.update');

    // Custom deduction routes
    Route::resource('deductions', \App\Http\Controllers\CustomDeductionController::class);
    Route::get('employees/{employee}/deductions', [\App\Http\Controllers\CustomDeductionController::class, 'employeeIndex'])->name('employees.deductions.index');

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
    Route::resource('payments', \App\Http\Controllers\PaymentScheduleController::class);
    Route::post('payments/{paymentSchedule}/pause', [\App\Http\Controllers\PaymentScheduleController::class, 'pause'])->name('payments.pause');
    Route::post('payments/{paymentSchedule}/resume', [\App\Http\Controllers\PaymentScheduleController::class, 'resume'])->name('payments.resume');
    Route::post('payments/{paymentSchedule}/cancel', [\App\Http\Controllers\PaymentScheduleController::class, 'cancel'])->name('payments.cancel');

    // Payment job routes
    Route::get('payments/jobs', [\App\Http\Controllers\PaymentJobController::class, 'index'])->name('payments.jobs');
    Route::get('payments/jobs/{paymentJob}', [\App\Http\Controllers\PaymentJobController::class, 'show'])->name('payments.jobs.show');

    // Payroll routes
    Route::get('payroll', [\App\Http\Controllers\PayrollController::class, 'index'])->name('payroll.index');
    Route::get('payroll/create', [\App\Http\Controllers\PayrollController::class, 'create'])->name('payroll.create');
    Route::post('payroll', [\App\Http\Controllers\PayrollController::class, 'store'])->name('payroll.store');
    Route::get('payroll/{payrollSchedule}/edit', [\App\Http\Controllers\PayrollController::class, 'edit'])->name('payroll.edit');
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
    Route::get('reports/export/csv', [\App\Http\Controllers\ReportController::class, 'exportCsv'])->name('reports.export.csv');
    Route::get('reports/export/excel', [\App\Http\Controllers\ReportController::class, 'exportExcel'])->name('reports.export.excel');
    Route::get('reports/export/pdf', [\App\Http\Controllers\ReportController::class, 'exportPdf'])->name('reports.export.pdf');

    // Audit log routes
    Route::get('audit-logs', [\App\Http\Controllers\AuditLogController::class, 'index'])->name('audit-logs.index');
    Route::get('audit-logs/{auditLog}', [\App\Http\Controllers\AuditLogController::class, 'show'])->name('audit-logs.show');

    // Escrow deposit routes
    Route::get('escrow/deposit', [\App\Http\Controllers\EscrowDepositController::class, 'index'])->name('escrow.deposit.index');
    Route::post('escrow/deposit', [\App\Http\Controllers\EscrowDepositController::class, 'store'])->name('escrow.deposit.store');
    Route::get('escrow/deposit/{id}', [\App\Http\Controllers\EscrowDepositController::class, 'show'])->name('escrow.deposit.show');

    // Billing routes
    Route::get('billing', [\App\Http\Controllers\BillingController::class, 'index'])->name('billing.index');
    Route::get('billing/{id}', [\App\Http\Controllers\BillingController::class, 'show'])->name('billing.show');

    // Admin escrow routes
    Route::prefix('admin/escrow')->name('admin.escrow.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\EscrowController::class, 'index'])->name('index');
        Route::post('/deposits', [\App\Http\Controllers\Admin\EscrowController::class, 'createDeposit'])->name('deposits.create');
        Route::post('/deposits/{deposit}/confirm', [\App\Http\Controllers\Admin\EscrowController::class, 'confirmDeposit'])->name('deposits.confirm');
        Route::post('/payments/{paymentJob}/fee-release', [\App\Http\Controllers\Admin\EscrowController::class, 'recordFeeRelease'])->name('payments.fee-release');
        Route::post('/payments/{paymentJob}/fund-return', [\App\Http\Controllers\Admin\EscrowController::class, 'recordFundReturn'])->name('payments.fund-return');
        Route::get('/balances', [\App\Http\Controllers\Admin\EscrowController::class, 'viewBalances'])->name('balances');
    });
});

require __DIR__.'/settings.php';
