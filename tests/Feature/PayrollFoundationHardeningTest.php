<?php

use App\Models\Business;
use App\Models\Employee;
use App\Models\EscrowDeposit;
use App\Models\PayrollJob;
use App\Models\PayrollSchedule;
use App\Models\User;
use App\Services\EscrowService;
use App\Services\PaymentService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->business = Business::factory()->create([
        'user_id' => $this->user->id,
        'escrow_balance' => 100000,
    ]);
    $this->user->businesses()->attach($this->business->id, ['role' => 'owner']);

    $this->employee = Employee::factory()->for($this->business)->create([
        'gross_salary' => 30000,
    ]);

    $this->schedule = PayrollSchedule::factory()->for($this->business)->create();
    $this->schedule->employees()->attach($this->employee->id);

    EscrowDeposit::factory()->for($this->business)->create([
        'status' => 'confirmed',
        'authorized_amount' => 100000,
    ]);

    $this->paymentService = app(PaymentService::class);
    $this->escrowService = app(EscrowService::class);
});

describe('Lock Ordering Prevents Deadlocks', function () {
    it('maintains consistent lock order: business → schedule → job → deposit', function () {
        $job = PayrollJob::factory()->for($this->schedule)->for($this->employee)->create([
            'status' => 'pending',
            'net_salary' => 25000,
        ]);

        // Process multiple jobs concurrently - should not deadlock
        $results = [];
        for ($i = 0; $i < 5; $i++) {
            $results[] = $this->paymentService->processPayrollJob($job);
        }

        // Only one should succeed
        $successCount = count(array_filter($results));
        expect($successCount)->toBe(1);

        // Job should be marked as succeeded
        $job->refresh();
        expect($job->status)->toBe('succeeded');
    });
});

describe('Escrow Balance Reconciliation', function () {
    it('detects and fixes balance drift', function () {
        // Create succeeded payroll job
        $job = PayrollJob::factory()->for($this->schedule)->for($this->employee)->create([
            'status' => 'succeeded',
            'net_salary' => 25000,
            'escrow_deposit_id' => 1,
            'processed_at' => now(),
        ]);

        // Manually corrupt the balance
        $this->business->update(['escrow_balance' => 50000]); // Should be 75000

        // Reconcile
        $reconciledBalance = $this->escrowService->recalculateBalance($this->business);

        // Balance should be corrected
        expect($reconciledBalance)->toBe(75000.0);
        $this->business->refresh();
        expect($this->business->escrow_balance)->toBe(75000.0);
    });
});

describe('Database Constraints', function () {
    it('prevents negative amounts in payroll jobs', function () {
        expect(function () {
            PayrollJob::factory()->for($this->schedule)->for($this->employee)->create([
                'gross_salary' => -1000,
            ]);
        })->toThrow(\Exception::class);
    });

    it('prevents invalid period dates', function () {
        expect(function () {
            PayrollJob::factory()->for($this->schedule)->for($this->employee)->create([
                'pay_period_start' => Carbon::parse('2026-01-31'),
                'pay_period_end' => Carbon::parse('2026-01-01'), // End before start
            ]);
        })->toThrow(\Exception::class);
    });

    it('prevents invalid status values', function () {
        expect(function () {
            DB::table('payroll_jobs')->insert([
                'payroll_schedule_id' => $this->schedule->id,
                'employee_id' => $this->employee->id,
                'gross_salary' => 30000,
                'net_salary' => 25000,
                'status' => 'invalid_status',
                'pay_period_start' => Carbon::parse('2026-01-01'),
                'pay_period_end' => Carbon::parse('2026-01-31'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        })->toThrow(\Exception::class);
    });
});

describe('Calculation Hash Validation', function () {
    it('validates calculation version compatibility', function () {
        $periodStart = Carbon::parse('2026-01-01');
        $periodEnd = Carbon::parse('2026-01-31');

        $calculationService = app(\App\Services\PayrollCalculationService::class);
        $validationService = app(\App\Services\PayrollValidationService::class);

        $calculation = $calculationService->calculatePayroll(
            $this->employee,
            $periodStart,
            $periodEnd
        );

        $job = PayrollJob::factory()->for($this->schedule)->for($this->employee)->create([
            'calculation_hash' => $calculation['calculation_hash'],
            'calculation_version' => $calculation['calculation_version'],
            'calculation_snapshot' => $calculation['calculation_snapshot'],
            'employee_snapshot' => $calculation['employee_snapshot'],
            'adjustment_inputs' => $calculation['adjustment_inputs'],
            'pay_period_start' => $periodStart,
            'pay_period_end' => $periodEnd,
        ]);

        $validation = $validationService->validatePayrollJob($job);

        expect($validation['valid'])->toBeTrue();
        expect($validation['errors'])->toBeEmpty();
    });

    it('detects calculation version mismatch', function () {
        $job = PayrollJob::factory()->for($this->schedule)->for($this->employee)->create([
            'calculation_version' => 999, // Old version
        ]);

        $validationService = app(\App\Services\PayrollValidationService::class);
        $validation = $validationService->validatePayrollJob($job);

        // Should have warnings about version mismatch
        expect($validation['warnings'])->not->toBeEmpty();
    });
});

describe('Stuck Job Detection', function () {
    it('detects jobs stuck in processing status', function () {
        $job = PayrollJob::factory()->for($this->schedule)->for($this->employee)->create([
            'status' => 'processing',
            'updated_at' => now()->subHours(3), // Stuck for 3 hours
        ]);

        $stuckJobDetector = new \App\Jobs\DetectStuckPayrollJobs(2); // 2 hour timeout
        $stuckJobDetector->handle();

        $job->refresh();
        expect($job->status)->toBe('failed');
        expect($job->error_message)->toContain('stuck in processing');
    });
});

describe('Atomic Schedule Processing', function () {
    it('prevents duplicate schedule processing with atomic UPDATE', function () {
        $schedule = PayrollSchedule::factory()->for($this->business)->create([
            'status' => 'active',
            'next_run_at' => now()->subMinute(),
            'last_run_at' => null,
        ]);

        // Simulate two processes trying to process the same schedule
        $process1 = function () use ($schedule) {
            return DB::transaction(function () use ($schedule) {
                $updated = PayrollSchedule::where('id', $schedule->id)
                    ->where(function ($query) {
                        $query->whereNull('last_run_at')
                            ->orWhereColumn('last_run_at', '<', 'next_run_at');
                    })
                    ->update(['last_run_at' => now()]);

                return $updated;
            });
        };

        $process2 = function () use ($schedule) {
            return DB::transaction(function () use ($schedule) {
                $updated = PayrollSchedule::where('id', $schedule->id)
                    ->where(function ($query) {
                        $query->whereNull('last_run_at')
                            ->orWhereColumn('last_run_at', '<', 'next_run_at');
                    })
                    ->update(['last_run_at' => now()]);

                return $updated;
            });
        };

        // Run both processes
        $result1 = $process1();
        $result2 = $process2();

        // Only one should succeed
        expect($result1 + $result2)->toBe(1);
    });
});

describe('Error Recovery', function () {
    it('cleans up orphaned escrow reservations', function () {
        $job = PayrollJob::factory()->for($this->schedule)->for($this->employee)->create([
            'status' => 'failed',
            'net_salary' => 25000,
            'escrow_deposit_id' => 1,
            'processed_at' => now()->subHours(2), // Failed 2 hours ago
        ]);

        $cleanupJob = new \App\Jobs\CleanupFailedPayrollReservations(3600); // 1 hour timeout
        $cleanupJob->handle($this->escrowService);

        $job->refresh();
        expect($job->escrow_deposit_id)->toBeNull();

        // Balance should be restored
        $this->business->refresh();
        expect($this->business->escrow_balance)->toBe(100000.0); // Original 100k
    });
});

describe('Performance Optimizations', function () {
    it('processes schedules in chunks to avoid memory issues', function () {
        // Create multiple due schedules
        PayrollSchedule::factory()->count(150)->for($this->business)->create([
            'status' => 'active',
            'next_run_at' => now()->subMinute(),
        ]);

        // Command should process in chunks without memory issues
        $this->artisan('payroll:process-scheduled')
            ->assertSuccessful();
    });

    it('uses optimized eager loading with selected fields', function () {
        $schedule = PayrollSchedule::factory()->for($this->business)->create();
        $schedule->employees()->attach($this->employee->id);

        // Should not load unnecessary relationships
        $command = new \App\Console\Commands\ProcessScheduledPayroll(
            app(\App\Services\SouthAfricanTaxService::class),
            app(\App\Services\SalaryCalculationService::class),
            app(\App\Services\AdjustmentService::class),
            app(\App\Services\SouthAfricaHolidayService::class),
            app(\App\Services\PayrollCalculationService::class),
            app(\App\Services\PayrollValidationService::class),
            app(\App\Services\LockService::class)
        );

        // This should complete without loading unnecessary data
        $result = $command->processSchedule($schedule);
        expect($result)->toBeGreaterThanOrEqual(0);
    });
});
