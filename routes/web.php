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
        // Get frequency - check for chart-specific frequencies first, then global
        $trendsFrequency = request()->get('trends_frequency') ?? request()->get('frequency', 'monthly');
        $successRateFrequency = request()->get('successRate_frequency') ?? request()->get('frequency', 'monthly');
        $dailyFrequency = request()->get('daily_frequency') ?? request()->get('frequency', 'monthly');
        $weeklyFrequency = request()->get('weekly_frequency') ?? request()->get('frequency', 'monthly');
        $frequency = request()->get('frequency', 'monthly'); // Global frequency for charts without specific override

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

        // Payment schedules
        $paymentScheduleQuery = \App\Models\PaymentSchedule::query();
        if ($businessId) {
            $paymentScheduleQuery->where('business_id', $businessId);
        } else {
            $paymentScheduleQuery->whereIn('business_id', $userBusinessIds);
        }

        // Payroll schedules
        $payrollScheduleQuery = \App\Models\PayrollSchedule::query();
        if ($businessId) {
            $payrollScheduleQuery->where('business_id', $businessId);
        } else {
            $payrollScheduleQuery->whereIn('business_id', $userBusinessIds);
        }

        $totalSchedules = $paymentScheduleQuery->count() + $payrollScheduleQuery->count();
        $activeSchedules = (clone $paymentScheduleQuery)->where('status', 'active')->count()
            + (clone $payrollScheduleQuery)->where('status', 'active')->count();

        // Payment jobs
        $paymentJobQuery = \App\Models\PaymentJob::query()
            ->whereHas('paymentSchedule', function ($q) use ($businessId, $userBusinessIds) {
                if ($businessId) {
                    $q->where('business_id', $businessId);
                } else {
                    $q->whereIn('business_id', $userBusinessIds);
                }
            });

        // Payroll jobs
        $payrollJobQuery = \App\Models\PayrollJob::query()
            ->whereHas('payrollSchedule', function ($q) use ($businessId, $userBusinessIds) {
                if ($businessId) {
                    $q->where('business_id', $businessId);
                } else {
                    $q->whereIn('business_id', $userBusinessIds);
                }
            });

        $pendingJobs = (clone $paymentJobQuery)->where('status', 'pending')->count()
            + (clone $payrollJobQuery)->where('status', 'pending')->count();
        $processingJobs = (clone $paymentJobQuery)->where('status', 'processing')->count()
            + (clone $payrollJobQuery)->where('status', 'processing')->count();
        $succeededJobs = (clone $paymentJobQuery)->where('status', 'succeeded')->count()
            + (clone $payrollJobQuery)->where('status', 'succeeded')->count();
        $failedJobs = (clone $paymentJobQuery)->where('status', 'failed')->count()
            + (clone $payrollJobQuery)->where('status', 'failed')->count();

        // Get upcoming payment schedules
        $upcomingPaymentSchedules = \App\Models\PaymentSchedule::query()
            ->whereIn('business_id', $businessId ? [$businessId] : $userBusinessIds)
            ->where('status', 'active')
            ->where('next_run_at', '>=', now())
            ->orderBy('next_run_at')
            ->with(['business', 'recipients'])
            ->get()
            ->map(function ($schedule) {
                return [
                    'id' => $schedule->id,
                    'name' => $schedule->name,
                    'next_run_at' => $schedule->next_run_at,
                    'amount' => $schedule->amount,
                    'currency' => $schedule->currency,
                    'type' => 'payment',
                    'recipients_count' => $schedule->recipients()->count(),
                ];
            })
            ->toArray();

        // Get upcoming payroll schedules
        $upcomingPayrollSchedules = \App\Models\PayrollSchedule::query()
            ->whereIn('business_id', $businessId ? [$businessId] : $userBusinessIds)
            ->where('status', 'active')
            ->where('next_run_at', '>=', now())
            ->orderBy('next_run_at')
            ->with(['business', 'employees'])
            ->get()
            ->map(function ($schedule) {
                return [
                    'id' => $schedule->id,
                    'name' => $schedule->name,
                    'next_run_at' => $schedule->next_run_at,
                    'amount' => null, // Payroll doesn't have a fixed amount
                    'currency' => 'ZAR', // Default currency
                    'type' => 'payroll',
                    'employees_count' => $schedule->employees()->count(),
                ];
            })
            ->toArray();

        // Merge arrays and convert to collection, sort by next_run_at, limit to 6
        $upcomingPayments = collect(array_merge($upcomingPaymentSchedules, $upcomingPayrollSchedules))
            ->sortBy('next_run_at')
            ->take(6)
            ->values();

        // Get recent payment jobs
        $recentPaymentJobs = \App\Models\PaymentJob::query()
            ->whereHas('paymentSchedule', function ($q) use ($businessId, $userBusinessIds) {
                if ($businessId) {
                    $q->where('business_id', $businessId);
                } else {
                    $q->whereIn('business_id', $userBusinessIds);
                }
            })
            ->with(['paymentSchedule', 'recipient'])
            ->latest()
            ->get()
            ->map(function ($job) {
                return [
                    'id' => $job->id,
                    'name' => $job->recipient?->name ?? 'Unknown',
                    'schedule_name' => $job->paymentSchedule?->name ?? 'Unknown',
                    'amount' => $job->amount,
                    'currency' => $job->currency,
                    'status' => $job->status,
                    'processed_at' => $job->processed_at,
                    'type' => 'payment',
                ];
            })
            ->toArray();

        // Get recent payroll jobs
        $recentPayrollJobs = \App\Models\PayrollJob::query()
            ->whereHas('payrollSchedule', function ($q) use ($businessId, $userBusinessIds) {
                if ($businessId) {
                    $q->where('business_id', $businessId);
                } else {
                    $q->whereIn('business_id', $userBusinessIds);
                }
            })
            ->with(['payrollSchedule', 'employee'])
            ->latest()
            ->get()
            ->map(function ($job) {
                return [
                    'id' => $job->id,
                    'name' => $job->employee?->name ?? 'Unknown',
                    'schedule_name' => $job->payrollSchedule?->name ?? 'Unknown',
                    'amount' => $job->net_salary,
                    'currency' => $job->currency,
                    'status' => $job->status,
                    'processed_at' => $job->processed_at,
                    'type' => 'payroll',
                ];
            })
            ->toArray();

        // Merge arrays and convert to collection, sort by processed_at, limit to 6
        $recentJobs = collect(array_merge($recentPaymentJobs, $recentPayrollJobs))
            ->sortByDesc('processed_at')
            ->take(4)
            ->values();

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

        $businessInfo = null;
        if ($selectedBusiness) {
            $escrowService = app(\App\Services\EscrowService::class);
            $escrowBalance = $escrowService->getAvailableBalance($selectedBusiness);

            $logoUrl = null;
            if ($selectedBusiness->logo) {
                $logoUrl = \Illuminate\Support\Facades\Storage::disk('public')->url($selectedBusiness->logo);
            }

            $businessInfo = [
                'id' => $selectedBusiness->id,
                'name' => $selectedBusiness->name,
                'logo' => $logoUrl,
                'status' => $selectedBusiness->status,
                'business_type' => $selectedBusiness->business_type,
                'email' => $selectedBusiness->email,
                'phone' => $selectedBusiness->phone,
                'escrow_balance' => (float) $escrowBalance,
                'employees_count' => \App\Models\Employee::where('business_id', $selectedBusiness->id)->count(),
                'payment_schedules_count' => $selectedBusiness->paymentSchedules()->count(),
                'payroll_schedules_count' => \App\Models\PayrollSchedule::where('business_id', $selectedBusiness->id)->count(),
                'recipients_count' => \App\Models\Recipient::where('business_id', $selectedBusiness->id)->count(),
            ];
        }

        // Calculate total businesses count (both relationships)
        $businessesCount = $user->businesses()->count() + $user->ownedBusinesses()->count();

        // Financial statistics for current month
        $currentMonthStart = now()->startOfMonth();
        $currentMonthEnd = now()->endOfMonth();

        $monthlyPaymentJobs = (clone $paymentJobQuery)
            ->whereBetween('processed_at', [$currentMonthStart, $currentMonthEnd])
            ->get();

        $monthlyPayrollJobs = (clone $payrollJobQuery)
            ->whereBetween('processed_at', [$currentMonthStart, $currentMonthEnd])
            ->get();

        $totalPaymentsThisMonth = $monthlyPaymentJobs->where('status', 'succeeded')->sum('amount');
        $totalPayrollThisMonth = $monthlyPayrollJobs->where('status', 'succeeded')->sum('net_salary');
        $totalFeesThisMonth = $monthlyPaymentJobs->where('status', 'succeeded')->sum('fee')
            + $monthlyPayrollJobs->where('status', 'succeeded')->sum('fee');

        $totalJobsThisMonth = $monthlyPaymentJobs->count() + $monthlyPayrollJobs->count();
        $succeededJobsThisMonth = $monthlyPaymentJobs->where('status', 'succeeded')->count()
            + $monthlyPayrollJobs->where('status', 'succeeded')->count();
        $successRate = $totalJobsThisMonth > 0 ? round(($succeededJobsThisMonth / $totalJobsThisMonth) * 100, 1) : 0;

        // Helper function to generate periods based on frequency
        $generatePeriods = function ($freq) {
            $periods = [];
            $labelKey = '';
            switch ($freq) {
                case 'weekly':
                    $periodCount = 12; // Last 12 weeks
                    for ($i = $periodCount - 1; $i >= 0; $i--) {
                        $weekStart = now()->subWeeks($i)->startOfWeek();
                        $weekEnd = now()->subWeeks($i)->endOfWeek();
                        $periods[] = [
                            'start' => $weekStart,
                            'end' => $weekEnd,
                            'label' => $weekStart->format('M d').' - '.$weekEnd->format('M d'),
                        ];
                    }
                    $labelKey = 'week';
                    break;
                case 'monthly':
                    $periodCount = 6; // Last 6 months
                    for ($i = $periodCount - 1; $i >= 0; $i--) {
                        $monthStart = now()->subMonths($i)->startOfMonth();
                        $monthEnd = now()->subMonths($i)->endOfMonth();
                        $periods[] = [
                            'start' => $monthStart,
                            'end' => $monthEnd,
                            'label' => $monthStart->format('M Y'),
                        ];
                    }
                    $labelKey = 'month';
                    break;
                case 'quarterly':
                    $periodCount = 8; // Last 8 quarters (2 years)
                    for ($i = $periodCount - 1; $i >= 0; $i--) {
                        $quarterStart = now()->subQuarters($i)->startOfQuarter();
                        $quarterEnd = now()->subQuarters($i)->endOfQuarter();
                        $quarterNum = ceil($quarterStart->month / 3);
                        $periods[] = [
                            'start' => $quarterStart,
                            'end' => $quarterEnd,
                            'label' => 'Q'.$quarterNum.' '.$quarterStart->format('Y'),
                        ];
                    }
                    $labelKey = 'quarter';
                    break;
                case 'yearly':
                    $periodCount = 5; // Last 5 years
                    for ($i = $periodCount - 1; $i >= 0; $i--) {
                        $yearStart = now()->subYears($i)->startOfYear();
                        $yearEnd = now()->subYears($i)->endOfYear();
                        $periods[] = [
                            'start' => $yearStart,
                            'end' => $yearEnd,
                            'label' => $yearStart->format('Y'),
                        ];
                    }
                    $labelKey = 'year';
                    break;
            }

            return ['periods' => $periods, 'labelKey' => $labelKey];
        };

        // Generate trends based on trends frequency
        $trendsData = $generatePeriods($trendsFrequency);
        $trendsPeriods = $trendsData['periods'];
        $trendsLabelKey = $trendsData['labelKey'];
        $monthlyTrends = [];

        foreach ($trendsPeriods as $period) {
            $periodPaymentJobs = \App\Models\PaymentJob::query()
                ->whereHas('paymentSchedule', function ($q) use ($businessId, $userBusinessIds) {
                    if ($businessId) {
                        $q->where('business_id', $businessId);
                    } else {
                        $q->whereIn('business_id', $userBusinessIds);
                    }
                })
                ->whereBetween('processed_at', [$period['start'], $period['end']])
                ->where('status', 'succeeded')
                ->get();

            $periodPayrollJobs = \App\Models\PayrollJob::query()
                ->whereHas('payrollSchedule', function ($q) use ($businessId, $userBusinessIds) {
                    if ($businessId) {
                        $q->where('business_id', $businessId);
                    } else {
                        $q->whereIn('business_id', $userBusinessIds);
                    }
                })
                ->whereBetween('processed_at', [$period['start'], $period['end']])
                ->where('status', 'succeeded')
                ->get();

            $monthlyTrends[] = [
                $trendsLabelKey => $period['label'],
                'payments' => (float) $periodPaymentJobs->sum('amount'),
                'payroll' => (float) $periodPayrollJobs->sum('net_salary'),
                'total' => (float) ($periodPaymentJobs->sum('amount') + $periodPayrollJobs->sum('net_salary')),
            ];
        }

        // Status breakdown for charts
        $statusBreakdown = [
            'succeeded' => $succeededJobs,
            'failed' => $failedJobs,
            'pending' => $pendingJobs,
            'processing' => $processingJobs,
        ];

        // Payment vs Payroll comparison
        $paymentJobsCount = (clone $paymentJobQuery)->where('status', 'succeeded')->count();
        $payrollJobsCount = (clone $payrollJobQuery)->where('status', 'succeeded')->count();

        // Daily trends for last 30 days
        $dailyTrends = [];
        for ($i = 29; $i >= 0; $i--) {
            $dayStart = now()->subDays($i)->startOfDay();
            $dayEnd = now()->subDays($i)->endOfDay();
            $dayName = $dayStart->format('M d');

            $dayPaymentJobs = \App\Models\PaymentJob::query()
                ->whereHas('paymentSchedule', function ($q) use ($businessId, $userBusinessIds) {
                    if ($businessId) {
                        $q->where('business_id', $businessId);
                    } else {
                        $q->whereIn('business_id', $userBusinessIds);
                    }
                })
                ->whereBetween('processed_at', [$dayStart, $dayEnd])
                ->where('status', 'succeeded')
                ->get();

            $dayPayrollJobs = \App\Models\PayrollJob::query()
                ->whereHas('payrollSchedule', function ($q) use ($businessId, $userBusinessIds) {
                    if ($businessId) {
                        $q->where('business_id', $businessId);
                    } else {
                        $q->whereIn('business_id', $userBusinessIds);
                    }
                })
                ->whereBetween('processed_at', [$dayStart, $dayEnd])
                ->where('status', 'succeeded')
                ->get();

            $dailyTrends[] = [
                'date' => $dayName,
                'payments' => (float) $dayPaymentJobs->sum('amount'),
                'payroll' => (float) $dayPayrollJobs->sum('net_salary'),
                'total' => (float) ($dayPaymentJobs->sum('amount') + $dayPayrollJobs->sum('net_salary')),
                'jobs_count' => $dayPaymentJobs->count() + $dayPayrollJobs->count(),
            ];
        }

        // Generate success rate trends based on success rate frequency
        $successRateData = $generatePeriods($successRateFrequency);
        $successRatePeriods = $successRateData['periods'];
        $successRateLabelKey = $successRateData['labelKey'];
        $successRateTrends = [];

        foreach ($successRatePeriods as $period) {
            $periodLabel = $period['label'];

            $periodAllPaymentJobs = \App\Models\PaymentJob::query()
                ->whereHas('paymentSchedule', function ($q) use ($businessId, $userBusinessIds) {
                    if ($businessId) {
                        $q->where('business_id', $businessId);
                    } else {
                        $q->whereIn('business_id', $userBusinessIds);
                    }
                })
                ->whereBetween('processed_at', [$period['start'], $period['end']])
                ->get();

            $periodAllPayrollJobs = \App\Models\PayrollJob::query()
                ->whereHas('payrollSchedule', function ($q) use ($businessId, $userBusinessIds) {
                    if ($businessId) {
                        $q->where('business_id', $businessId);
                    } else {
                        $q->whereIn('business_id', $userBusinessIds);
                    }
                })
                ->whereBetween('processed_at', [$period['start'], $period['end']])
                ->get();

            $totalPeriodJobs = $periodAllPaymentJobs->count() + $periodAllPayrollJobs->count();
            $succeededPeriodJobs = $periodAllPaymentJobs->where('status', 'succeeded')->count()
                + $periodAllPayrollJobs->where('status', 'succeeded')->count();
            $failedPeriodJobs = $periodAllPaymentJobs->where('status', 'failed')->count()
                + $periodAllPayrollJobs->where('status', 'failed')->count();

            $successRateTrends[] = [
                $successRateLabelKey => $periodLabel,
                'success_rate' => $totalPeriodJobs > 0 ? round(($succeededPeriodJobs / $totalPeriodJobs) * 100, 1) : 0,
                'succeeded' => $succeededPeriodJobs,
                'failed' => $failedPeriodJobs,
                'total' => $totalPeriodJobs,
            ];
        }

        // Top recipients by volume (last 30 days)
        $topRecipients = \App\Models\PaymentJob::query()
            ->whereHas('paymentSchedule', function ($q) use ($businessId, $userBusinessIds) {
                if ($businessId) {
                    $q->where('business_id', $businessId);
                } else {
                    $q->whereIn('business_id', $userBusinessIds);
                }
            })
            ->whereBetween('processed_at', [now()->subDays(30)->startOfDay(), now()->endOfDay()])
            ->where('status', 'succeeded')
            ->with('recipient')
            ->get()
            ->groupBy('recipient_id')
            ->map(function ($jobs, $recipientId) {
                $recipient = $jobs->first()->recipient;

                return [
                    'name' => $recipient?->name ?? 'Unknown',
                    'total_amount' => (float) $jobs->sum('amount'),
                    'jobs_count' => $jobs->count(),
                    'average_amount' => (float) $jobs->avg('amount'),
                ];
            })
            ->sortByDesc('total_amount')
            ->take(10)
            ->values();

        // Top employees by volume (last 30 days)
        $topEmployees = \App\Models\PayrollJob::query()
            ->whereHas('payrollSchedule', function ($q) use ($businessId, $userBusinessIds) {
                if ($businessId) {
                    $q->where('business_id', $businessId);
                } else {
                    $q->whereIn('business_id', $userBusinessIds);
                }
            })
            ->whereBetween('processed_at', [now()->subDays(30)->startOfDay(), now()->endOfDay()])
            ->where('status', 'succeeded')
            ->with('employee')
            ->get()
            ->groupBy('employee_id')
            ->map(function ($jobs, $employeeId) {
                $employee = $jobs->first()->employee;

                return [
                    'name' => $employee?->name ?? 'Unknown',
                    'total_amount' => (float) $jobs->sum('net_salary'),
                    'jobs_count' => $jobs->count(),
                    'average_amount' => (float) $jobs->avg('net_salary'),
                ];
            })
            ->sortByDesc('total_amount')
            ->take(10)
            ->values();

        // This month vs last month comparison
        $lastMonthStart = now()->subMonth()->startOfMonth();
        $lastMonthEnd = now()->subMonth()->endOfMonth();

        $lastMonthPaymentJobs = \App\Models\PaymentJob::query()
            ->whereHas('paymentSchedule', function ($q) use ($businessId, $userBusinessIds) {
                if ($businessId) {
                    $q->where('business_id', $businessId);
                } else {
                    $q->whereIn('business_id', $userBusinessIds);
                }
            })
            ->whereBetween('processed_at', [$lastMonthStart, $lastMonthEnd])
            ->where('status', 'succeeded')
            ->get();

        $lastMonthPayrollJobs = \App\Models\PayrollJob::query()
            ->whereHas('payrollSchedule', function ($q) use ($businessId, $userBusinessIds) {
                if ($businessId) {
                    $q->where('business_id', $businessId);
                } else {
                    $q->whereIn('business_id', $userBusinessIds);
                }
            })
            ->whereBetween('processed_at', [$lastMonthStart, $lastMonthEnd])
            ->where('status', 'succeeded')
            ->get();

        $lastMonthTotal = (float) ($lastMonthPaymentJobs->sum('amount') + $lastMonthPayrollJobs->sum('net_salary'));
        $thisMonthTotal = (float) ($totalPaymentsThisMonth + $totalPayrollThisMonth);
        $monthOverMonthGrowth = $lastMonthTotal > 0
            ? round((($thisMonthTotal - $lastMonthTotal) / $lastMonthTotal) * 100, 1)
            : 0;

        // Average transaction amounts
        $avgPaymentAmount = $monthlyPaymentJobs->where('status', 'succeeded')->avg('amount') ?? 0;
        $avgPayrollAmount = $monthlyPayrollJobs->where('status', 'succeeded')->avg('net_salary') ?? 0;

        // Weekly trends (last 12 weeks)
        $weeklyTrends = [];
        for ($i = 11; $i >= 0; $i--) {
            $weekStart = now()->subWeeks($i)->startOfWeek();
            $weekEnd = now()->subWeeks($i)->endOfWeek();
            $weekName = $weekStart->format('M d').' - '.$weekEnd->format('M d');

            $weekPaymentJobs = \App\Models\PaymentJob::query()
                ->whereHas('paymentSchedule', function ($q) use ($businessId, $userBusinessIds) {
                    if ($businessId) {
                        $q->where('business_id', $businessId);
                    } else {
                        $q->whereIn('business_id', $userBusinessIds);
                    }
                })
                ->whereBetween('processed_at', [$weekStart, $weekEnd])
                ->where('status', 'succeeded')
                ->get();

            $weekPayrollJobs = \App\Models\PayrollJob::query()
                ->whereHas('payrollSchedule', function ($q) use ($businessId, $userBusinessIds) {
                    if ($businessId) {
                        $q->where('business_id', $businessId);
                    } else {
                        $q->whereIn('business_id', $userBusinessIds);
                    }
                })
                ->whereBetween('processed_at', [$weekStart, $weekEnd])
                ->where('status', 'succeeded')
                ->get();

            $weeklyTrends[] = [
                'week' => $weekName,
                'payments' => (float) $weekPaymentJobs->sum('amount'),
                'payroll' => (float) $weekPayrollJobs->sum('net_salary'),
                'total' => (float) ($weekPaymentJobs->sum('amount') + $weekPayrollJobs->sum('net_salary')),
            ];
        }

        return Inertia::render('dashboard', [
            'metrics' => [
                'total_schedules' => $totalSchedules,
                'active_schedules' => $activeSchedules,
                'pending_jobs' => $pendingJobs,
                'processing_jobs' => $processingJobs,
                'succeeded_jobs' => $succeededJobs,
                'failed_jobs' => $failedJobs,
            ],
            'financial' => [
                'total_payments_this_month' => (float) $totalPaymentsThisMonth,
                'total_payroll_this_month' => (float) $totalPayrollThisMonth,
                'total_fees_this_month' => (float) $totalFeesThisMonth,
                'total_processed_this_month' => (float) ($totalPaymentsThisMonth + $totalPayrollThisMonth),
                'success_rate' => $successRate,
                'total_jobs_this_month' => $totalJobsThisMonth,
            ],
            'monthlyTrends' => $monthlyTrends,
            'statusBreakdown' => $statusBreakdown,
            'jobTypeComparison' => [
                'payments' => $paymentJobsCount,
                'payroll' => $payrollJobsCount,
            ],
            'dailyTrends' => $dailyTrends,
            'weeklyTrends' => $weeklyTrends,
            'successRateTrends' => $successRateTrends,
            'topRecipients' => $topRecipients,
            'topEmployees' => $topEmployees,
            'monthOverMonthGrowth' => $monthOverMonthGrowth,
            'avgPaymentAmount' => (float) $avgPaymentAmount,
            'avgPayrollAmount' => (float) $avgPayrollAmount,
            'upcomingPayments' => $upcomingPayments,
            'recentJobs' => $recentJobs,
            'escrowBalance' => $escrowBalance,
            'selectedBusiness' => $selectedBusiness ? ['id' => $selectedBusiness->id, 'name' => $selectedBusiness->name] : null,
            'businessInfo' => $businessInfo,
            'businessesCount' => $businessesCount,
        ]);
    })->name('dashboard');

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
    // Payment job routes (must be before resource route to avoid conflict)
    Route::get('payments/jobs', [\App\Http\Controllers\PaymentJobController::class, 'index'])->name('payments.jobs');
    Route::get('payments/jobs/{paymentJob}', [\App\Http\Controllers\PaymentJobController::class, 'show'])->name('payments.jobs.show');

    Route::resource('payments', \App\Http\Controllers\PaymentScheduleController::class);
    Route::post('payments/{paymentSchedule}/pause', [\App\Http\Controllers\PaymentScheduleController::class, 'pause'])->name('payments.pause');
    Route::post('payments/{paymentSchedule}/resume', [\App\Http\Controllers\PaymentScheduleController::class, 'resume'])->name('payments.resume');
    Route::post('payments/{paymentSchedule}/cancel', [\App\Http\Controllers\PaymentScheduleController::class, 'cancel'])->name('payments.cancel');

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

    // Admin escrow routes
    Route::prefix('admin/escrow')->name('admin.escrow.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\EscrowController::class, 'index'])->name('index');
        Route::post('/deposits', [\App\Http\Controllers\Admin\EscrowController::class, 'createDeposit'])->name('deposits.create');
        Route::post('/deposits/{deposit}/confirm', [\App\Http\Controllers\Admin\EscrowController::class, 'confirmDeposit'])->name('deposits.confirm');
        Route::post('/payments/{paymentJob}/fee-release', [\App\Http\Controllers\Admin\EscrowController::class, 'recordFeeRelease'])->name('payments.fee-release');
        Route::post('/payments/{paymentJob}/fund-return', [\App\Http\Controllers\Admin\EscrowController::class, 'recordFundReturn'])->name('payments.fund-return');
        Route::get('/balances', [\App\Http\Controllers\Admin\EscrowController::class, 'viewBalances'])->name('balances');
    });

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

require __DIR__.'/settings.php';
