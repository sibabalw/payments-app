<?php

use App\Models\Adjustment;
use App\Models\Business;
use App\Models\Employee;
use App\Models\PayrollSchedule;
use App\Models\User;
use App\Services\AdjustmentService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->business = Business::factory()->create([
        'user_id' => $this->user->id,
    ]);
    $this->user->businesses()->attach($this->business->id, ['role' => 'owner']);
    $this->user->update(['current_business_id' => $this->business->id]);

    $this->employee = Employee::factory()->for($this->business)->create([
        'gross_salary' => 30000,
    ]);

    $this->adjustmentService = app(AdjustmentService::class);
});

describe('PayrollSchedule::calculatePayPeriod', function () {
    it('calculates previous month for monthly recurring schedules', function () {
        // Monthly schedule with cron "0 8 25 * *" (runs on 25th at 8am)
        $schedule = PayrollSchedule::factory()->for($this->business)->create([
            'frequency' => '0 8 25 * *',
            'schedule_type' => 'recurring',
            'next_run_at' => Carbon::parse('2026-02-25 08:00:00'),
        ]);

        $period = $schedule->calculatePayPeriod();

        // Should return January (previous month)
        expect($period['start']->format('Y-m-d'))->toBe('2026-01-01');
        expect($period['end']->format('Y-m-d'))->toBe('2026-01-31');
    });

    it('calculates current month for one-time schedules', function () {
        $schedule = PayrollSchedule::factory()->for($this->business)->oneTime()->create([
            'next_run_at' => Carbon::parse('2026-01-15 10:00:00'),
        ]);

        $period = $schedule->calculatePayPeriod();

        // Should return January (current month)
        expect($period['start']->format('Y-m-d'))->toBe('2026-01-01');
        expect($period['end']->format('Y-m-d'))->toBe('2026-01-31');
    });

    it('throws when both execution date and next_run_at are null', function () {
        $schedule = PayrollSchedule::factory()->for($this->business)->create([
            'next_run_at' => null,
        ]);

        $schedule->calculatePayPeriod();
    })->throws(\RuntimeException::class, 'Pay period cannot be calculated');
});

describe('Employee-specific once-off adjustment matching', function () {
    it('matches employee-specific adjustment only to the correct schedule', function () {
        // Create two schedules that both run in January
        // Schedule A: Monthly payroll (runs Jan 25th, pays for December)
        $scheduleA = PayrollSchedule::factory()->for($this->business)->create([
            'name' => 'Monthly Payroll',
            'frequency' => '0 8 25 * *',
            'schedule_type' => 'recurring',
            'next_run_at' => Carbon::parse('2026-01-25 08:00:00'),
        ]);
        $scheduleA->employees()->attach($this->employee->id);

        // Schedule B: One-time bonus (runs Jan 15th)
        $scheduleB = PayrollSchedule::factory()->for($this->business)->oneTime()->create([
            'name' => 'January Bonus',
            'next_run_at' => Carbon::parse('2026-01-15 10:00:00'),
        ]);
        $scheduleB->employees()->attach($this->employee->id);

        // Create employee-specific once-off adjustment for Schedule A
        $periodA = $scheduleA->calculatePayPeriod();
        $adjustmentForA = Adjustment::create([
            'business_id' => $this->business->id,
            'employee_id' => $this->employee->id,
            'payroll_schedule_id' => $scheduleA->id,
            'name' => 'December Bonus',
            'type' => 'fixed',
            'amount' => 5000,
            'adjustment_type' => 'addition',
            'is_recurring' => false,
            'payroll_period_start' => $periodA['start'],
            'payroll_period_end' => $periodA['end'],
            'is_active' => true,
        ]);

        // Get adjustments for Schedule A (should include the adjustment)
        $adjustmentsForScheduleA = $this->adjustmentService->getValidAdjustments(
            $this->employee,
            $periodA['start'],
            $periodA['end'],
            $scheduleA->id
        );

        // Get adjustments for Schedule B (should NOT include the adjustment)
        $periodB = $scheduleB->calculatePayPeriod();
        $adjustmentsForScheduleB = $this->adjustmentService->getValidAdjustments(
            $this->employee,
            $periodB['start'],
            $periodB['end'],
            $scheduleB->id
        );

        expect($adjustmentsForScheduleA)->toHaveCount(1);
        expect($adjustmentsForScheduleA->first()->id)->toBe($adjustmentForA->id);
        expect($adjustmentsForScheduleB)->toHaveCount(0);
    });

    it('does not apply employee-specific adjustment to other schedules with same period', function () {
        // Create multiple schedules with the same period (all one-time in January)
        $scheduleA = PayrollSchedule::factory()->for($this->business)->oneTime()->create([
            'name' => 'Bonus A',
            'next_run_at' => Carbon::parse('2026-01-10 08:00:00'),
        ]);
        $scheduleA->employees()->attach($this->employee->id);

        $scheduleB = PayrollSchedule::factory()->for($this->business)->oneTime()->create([
            'name' => 'Bonus B',
            'next_run_at' => Carbon::parse('2026-01-15 08:00:00'),
        ]);
        $scheduleB->employees()->attach($this->employee->id);

        $scheduleC = PayrollSchedule::factory()->for($this->business)->oneTime()->create([
            'name' => 'Bonus C',
            'next_run_at' => Carbon::parse('2026-01-20 08:00:00'),
        ]);
        $scheduleC->employees()->attach($this->employee->id);

        // All schedules calculate the same period (January)
        $periodA = $scheduleA->calculatePayPeriod();
        $periodB = $scheduleB->calculatePayPeriod();
        $periodC = $scheduleC->calculatePayPeriod();

        expect($periodA['start']->format('Y-m-d'))->toBe($periodB['start']->format('Y-m-d'));
        expect($periodA['start']->format('Y-m-d'))->toBe($periodC['start']->format('Y-m-d'));

        // Create adjustment only for Schedule B
        $adjustment = Adjustment::create([
            'business_id' => $this->business->id,
            'employee_id' => $this->employee->id,
            'payroll_schedule_id' => $scheduleB->id,
            'name' => 'Special Bonus',
            'type' => 'fixed',
            'amount' => 2000,
            'adjustment_type' => 'addition',
            'is_recurring' => false,
            'payroll_period_start' => $periodB['start'],
            'payroll_period_end' => $periodB['end'],
            'is_active' => true,
        ]);

        // Only Schedule B should get the adjustment
        $adjustmentsA = $this->adjustmentService->getValidAdjustments($this->employee, $periodA['start'], $periodA['end'], $scheduleA->id);
        $adjustmentsB = $this->adjustmentService->getValidAdjustments($this->employee, $periodB['start'], $periodB['end'], $scheduleB->id);
        $adjustmentsC = $this->adjustmentService->getValidAdjustments($this->employee, $periodC['start'], $periodC['end'], $scheduleC->id);

        expect($adjustmentsA)->toHaveCount(0);
        expect($adjustmentsB)->toHaveCount(1);
        expect($adjustmentsC)->toHaveCount(0);
    });

    it('applies recurring adjustments to all schedules', function () {
        $scheduleA = PayrollSchedule::factory()->for($this->business)->oneTime()->create([
            'next_run_at' => Carbon::parse('2026-01-10 08:00:00'),
        ]);
        $scheduleA->employees()->attach($this->employee->id);

        $scheduleB = PayrollSchedule::factory()->for($this->business)->oneTime()->create([
            'next_run_at' => Carbon::parse('2026-01-15 08:00:00'),
        ]);
        $scheduleB->employees()->attach($this->employee->id);

        // Create recurring adjustment (no schedule or period)
        $recurringAdjustment = Adjustment::create([
            'business_id' => $this->business->id,
            'employee_id' => $this->employee->id,
            'payroll_schedule_id' => null,
            'name' => 'Medical Aid',
            'type' => 'fixed',
            'amount' => 500,
            'adjustment_type' => 'deduction',
            'is_recurring' => true,
            'payroll_period_start' => null,
            'payroll_period_end' => null,
            'is_active' => true,
        ]);

        $periodA = $scheduleA->calculatePayPeriod();
        $periodB = $scheduleB->calculatePayPeriod();

        $adjustmentsA = $this->adjustmentService->getValidAdjustments($this->employee, $periodA['start'], $periodA['end'], $scheduleA->id);
        $adjustmentsB = $this->adjustmentService->getValidAdjustments($this->employee, $periodB['start'], $periodB['end'], $scheduleB->id);

        // Both schedules should get the recurring adjustment
        expect($adjustmentsA)->toHaveCount(1);
        expect($adjustmentsA->first()->id)->toBe($recurringAdjustment->id);
        expect($adjustmentsB)->toHaveCount(1);
        expect($adjustmentsB->first()->id)->toBe($recurringAdjustment->id);
    });

    it('requires exact period match for employee-specific once-off adjustments', function () {
        $schedule = PayrollSchedule::factory()->for($this->business)->oneTime()->create([
            'next_run_at' => Carbon::parse('2026-01-15 08:00:00'),
        ]);
        $schedule->employees()->attach($this->employee->id);

        // Create adjustment with slightly different period
        $adjustment = Adjustment::create([
            'business_id' => $this->business->id,
            'employee_id' => $this->employee->id,
            'payroll_schedule_id' => $schedule->id,
            'name' => 'Test Adjustment',
            'type' => 'fixed',
            'amount' => 1000,
            'adjustment_type' => 'addition',
            'is_recurring' => false,
            'payroll_period_start' => '2026-01-02', // Different from Jan 1
            'payroll_period_end' => '2026-01-31',
            'is_active' => true,
        ]);

        // Query with the schedule's calculated period (Jan 1-31)
        $period = $schedule->calculatePayPeriod();
        $adjustments = $this->adjustmentService->getValidAdjustments(
            $this->employee,
            $period['start'],
            $period['end'],
            $schedule->id
        );

        // Should NOT match because period start is different
        expect($adjustments)->toHaveCount(0);
    });
});

describe('Adjustment API endpoint', function () {
    it('calculates period for a schedule', function () {
        $schedule = PayrollSchedule::factory()->for($this->business)->create([
            'frequency' => '0 8 25 * *',
            'schedule_type' => 'recurring',
            'next_run_at' => Carbon::parse('2026-02-25 08:00:00'),
        ]);

        $response = $this->actingAs($this->user)->get("/adjustments/calculate-period?payroll_schedule_id={$schedule->id}");

        $response->assertOk();
        $response->assertJson([
            'payroll_period_start' => '2026-01-01',
            'payroll_period_end' => '2026-01-31',
        ]);
    });
});

describe('Duplicate prevention', function () {
    it('prevents creating duplicate employee-specific once-off adjustments', function () {
        $schedule = PayrollSchedule::factory()->for($this->business)->oneTime()->create([
            'next_run_at' => Carbon::parse('2026-01-15 08:00:00'),
        ]);
        $schedule->employees()->attach($this->employee->id);

        // Create first adjustment
        $this->actingAs($this->user)->post('/adjustments', [
            'business_id' => $this->business->id,
            'employee_id' => $this->employee->id,
            'payroll_schedule_id' => $schedule->id,
            'name' => 'First Bonus',
            'type' => 'fixed',
            'amount' => 1000,
            'adjustment_type' => 'addition',
            'is_recurring' => false,
            'payroll_period_start' => '2026-01-01',
            'payroll_period_end' => '2026-01-31',
            'is_active' => true,
        ])->assertRedirect();

        // Try to create duplicate
        $response = $this->actingAs($this->user)->post('/adjustments', [
            'business_id' => $this->business->id,
            'employee_id' => $this->employee->id,
            'payroll_schedule_id' => $schedule->id,
            'name' => 'Second Bonus',
            'type' => 'fixed',
            'amount' => 2000,
            'adjustment_type' => 'addition',
            'is_recurring' => false,
            'payroll_period_start' => '2026-01-01',
            'payroll_period_end' => '2026-01-31',
            'is_active' => true,
        ]);

        $response->assertSessionHasErrors(['payroll_schedule_id']);

        // Should only have one adjustment
        expect(Adjustment::where('employee_id', $this->employee->id)->count())->toBe(1);
    });

    it('allows different adjustments for different schedules same employee', function () {
        $scheduleA = PayrollSchedule::factory()->for($this->business)->oneTime()->create([
            'name' => 'Schedule A',
            'next_run_at' => Carbon::parse('2026-01-10 08:00:00'),
        ]);
        $scheduleA->employees()->attach($this->employee->id);

        $scheduleB = PayrollSchedule::factory()->for($this->business)->oneTime()->create([
            'name' => 'Schedule B',
            'next_run_at' => Carbon::parse('2026-01-15 08:00:00'),
        ]);
        $scheduleB->employees()->attach($this->employee->id);

        // Create adjustment for Schedule A
        $this->actingAs($this->user)->post('/adjustments', [
            'business_id' => $this->business->id,
            'employee_id' => $this->employee->id,
            'payroll_schedule_id' => $scheduleA->id,
            'name' => 'Bonus A',
            'type' => 'fixed',
            'amount' => 1000,
            'adjustment_type' => 'addition',
            'is_recurring' => false,
            'payroll_period_start' => '2026-01-01',
            'payroll_period_end' => '2026-01-31',
            'is_active' => true,
        ])->assertRedirect();

        // Create adjustment for Schedule B (should succeed)
        $this->actingAs($this->user)->post('/adjustments', [
            'business_id' => $this->business->id,
            'employee_id' => $this->employee->id,
            'payroll_schedule_id' => $scheduleB->id,
            'name' => 'Bonus B',
            'type' => 'fixed',
            'amount' => 2000,
            'adjustment_type' => 'addition',
            'is_recurring' => false,
            'payroll_period_start' => '2026-01-01',
            'payroll_period_end' => '2026-01-31',
            'is_active' => true,
        ])->assertRedirect();

        // Should have two adjustments
        expect(Adjustment::where('employee_id', $this->employee->id)->count())->toBe(2);
    });
});
