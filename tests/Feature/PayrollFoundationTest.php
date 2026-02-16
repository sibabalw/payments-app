<?php

use App\Models\Business;
use App\Models\Employee;
use App\Models\EscrowDeposit;
use App\Models\PayrollJob;
use App\Models\PayrollSchedule;
use App\Models\User;
use App\Services\EscrowService;
use App\Services\PayrollCalculationService;
use App\Services\PayrollValidationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

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

    $this->calculationService = app(PayrollCalculationService::class);
    $this->validationService = app(PayrollValidationService::class);
    $this->escrowService = app(EscrowService::class);
});

describe('Status Transition Validation', function () {
    it('allows valid status transitions', function () {
        $job = PayrollJob::factory()->pending()->create([
            'employee_id' => $this->employee->id,
        ]);

        expect($job->isValidTransition('pending', 'processing'))->toBeTrue();
        expect($job->isValidTransition('pending', 'failed'))->toBeTrue();
        expect($job->isValidTransition('processing', 'succeeded'))->toBeTrue();
        expect($job->isValidTransition('processing', 'failed'))->toBeTrue();
    });

    it('prevents invalid status transitions', function () {
        $job = PayrollJob::factory()->succeeded()->create([
            'employee_id' => $this->employee->id,
        ]);

        expect($job->isValidTransition('succeeded', 'pending'))->toBeFalse();
        expect($job->isValidTransition('succeeded', 'processing'))->toBeFalse();
    });

    it('throws exception on invalid status update', function () {
        $job = PayrollJob::factory()->succeeded()->create([
            'employee_id' => $this->employee->id,
        ]);

        expect(fn () => $job->updateStatus('pending'))
            ->toThrow(\RuntimeException::class, 'Invalid status transition');
    });
});

describe('Period Overlap Detection', function () {
    it('detects overlapping pay periods', function () {
        $schedule = PayrollSchedule::factory()->for($this->business)->create();
        $periodStart = Carbon::parse('2026-01-01');
        $periodEnd = Carbon::parse('2026-01-31');

        // Create existing job for the same period
        PayrollJob::factory()->for($schedule)->for($this->employee)->create([
            'pay_period_start' => $periodStart,
            'pay_period_end' => $periodEnd,
            'status' => 'succeeded',
        ]);

        $overlapCheck = $this->validationService->checkPeriodOverlap(
            $this->employee,
            $periodStart,
            $periodEnd
        );

        expect($overlapCheck['has_overlap'])->toBeTrue();
        expect($overlapCheck['overlapping_jobs'])->toHaveCount(1);
    });

    it('allows non-overlapping periods', function () {
        $schedule = PayrollSchedule::factory()->for($this->business)->create();
        $periodStart = Carbon::parse('2026-01-01');
        $periodEnd = Carbon::parse('2026-01-31');

        // Create job for different period
        PayrollJob::factory()->for($schedule)->for($this->employee)->create([
            'pay_period_start' => Carbon::parse('2026-02-01'),
            'pay_period_end' => Carbon::parse('2026-02-28'),
            'status' => 'succeeded',
        ]);

        $overlapCheck = $this->validationService->checkPeriodOverlap(
            $this->employee,
            $periodStart,
            $periodEnd
        );

        expect($overlapCheck['has_overlap'])->toBeFalse();
    });
});

describe('Escrow Balance Calculation', function () {
    it('uses net_salary not gross_salary for payroll jobs', function () {
        // Create escrow deposit (amount must be >= authorized_amount per DB constraint)
        EscrowDeposit::factory()->for($this->business)->create([
            'status' => 'confirmed',
            'amount' => 100000,
            'authorized_amount' => 100000,
        ]);

        $schedule = PayrollSchedule::factory()->for($this->business)->create();
        $grossSalary = 30000;
        $netSalary = 25000; // After deductions

        // Create succeeded payroll job
        PayrollJob::factory()->for($schedule)->for($this->employee)->create([
            'gross_salary' => $grossSalary,
            'net_salary' => $netSalary,
            'status' => 'succeeded',
            'escrow_deposit_id' => 1,
            'processed_at' => now(),
        ]);

        // Recalculate balance
        $calculatedBalance = $this->escrowService->recalculateBalance($this->business);

        // Should use net_salary (25000), not gross_salary (30000)
        // Balance = 100000 - 25000 = 75000
        expect($calculatedBalance)->toBe(75000.0);
    });
});

describe('Calculation Hash Validation', function () {
    it('validates calculation hash using snapshot employee ID', function () {
        $periodStart = Carbon::parse('2026-01-01');
        $periodEnd = Carbon::parse('2026-01-31');

        $calculation = $this->calculationService->calculatePayroll(
            $this->employee,
            $periodStart,
            $periodEnd
        );

        $schedule = PayrollSchedule::factory()->for($this->business)->create();
        $job = PayrollJob::factory()->for($schedule)->for($this->employee)->create([
            'calculation_hash' => $calculation['calculation_hash'],
            'calculation_snapshot' => $calculation['calculation_snapshot'],
            'employee_snapshot' => $calculation['employee_snapshot'],
            'pay_period_start' => $periodStart,
            'pay_period_end' => $periodEnd,
        ]);

        $validation = $this->validationService->validatePayrollJob($job);

        // Should pass validation
        expect($validation['valid'])->toBeTrue();
        expect($validation['errors'])->toBeEmpty();
    });
});

describe('Immutable Field Protection', function () {
    it('prevents updating immutable fields after creation', function () {
        $job = PayrollJob::factory()->pending()->create([
            'employee_id' => $this->employee->id,
            'gross_salary' => 30000,
            'net_salary' => 25000,
        ]);

        expect(fn () => $job->update(['gross_salary' => 35000]))
            ->toThrow(\RuntimeException::class, 'Cannot update immutable calculation fields');
    });

    it('allows updating mutable fields', function () {
        $job = PayrollJob::factory()->pending()->create([
            'employee_id' => $this->employee->id,
        ]);

        $result = $job->updateStatus('processing');

        expect($result)->toBeTrue();
        expect($job->fresh()->status)->toBe('processing');
    });
});

describe('Recalculation Restrictions', function () {
    it('prevents recalculation of succeeded jobs without create-new flag', function () {
        $job = PayrollJob::factory()->succeeded()->create([
            'employee_id' => $this->employee->id,
        ]);

        $result = $this->artisan('payroll:recalculate', ['job' => $job->id]);
        $result->assertFailed();
    });

    it('allows recalculation of pending jobs', function () {
        $job = PayrollJob::factory()->pending()->create([
            'employee_id' => $this->employee->id,
        ]);

        $this->artisan('payroll:recalculate', [
            'job' => $job->id,
            '--force' => true,
        ])
            ->expectsConfirmation('Update existing job with recalculated values?', 'yes')
            ->assertSuccessful();
    });
});
