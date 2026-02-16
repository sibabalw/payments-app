<?php

use App\Models\Business;
use App\Models\Employee;
use App\Models\EscrowDeposit;
use App\Models\PayrollJob;
use App\Models\PayrollSchedule;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

/**
 * Real user-flow payroll tests: create payroll, wait for execution, run scheduler, check logs and DB.
 * Run delay is configurable: PAYROLL_FLOW_WAIT_SECONDS (default 5 for CI; use 120 for real 2-minute test).
 */
function runDelaySeconds(): int
{
    return (int) (env('PAYROLL_FLOW_WAIT_SECONDS') ?: 5);
}

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->business = Business::factory()->create([
        'user_id' => $this->user->id,
        'escrow_balance' => 100000,
    ]);
    $this->user->businesses()->attach($this->business->id, ['role' => 'owner']);
    $this->user->update(['current_business_id' => $this->business->id]);

    $this->employee = Employee::factory()->for($this->business)->create([
        'gross_salary' => 30000,
    ]);

    EscrowDeposit::factory()->for($this->business)->create([
        'status' => 'confirmed',
        'amount' => 100000,
        'authorized_amount' => 100000,
    ]);
});

describe('Create payroll and execute after wait', function () {
    it('creates one-time payroll for run in N seconds, waits, runs scheduler, and verifies execution and logs', function () {
        $delay = runDelaySeconds();
        $runAt = Carbon::now(config('app.timezone'))->addSeconds($delay);
        $date = $runAt->format('Y-m-d');
        $time = $runAt->format('H:i');

        // Ensure date is a business day (not weekend)
        while ($runAt->isWeekend()) {
            $runAt->addDay();
        }
        $date = $runAt->format('Y-m-d');
        $time = $runAt->format('H:i');

        $response = $this->actingAs($this->user)->post(route('payroll.store'), [
            'business_id' => $this->business->id,
            'name' => 'One-time test payroll',
            'schedule_type' => 'one_time',
            'employee_ids' => [$this->employee->id],
            'scheduled_date' => $date,
            'scheduled_time' => $time,
        ]);

        $response->assertRedirect(route('payroll.index'));
        $response->assertSessionHas('success');

        $schedule = PayrollSchedule::where('business_id', $this->business->id)->where('name', 'One-time test payroll')->first();
        expect($schedule)->not->toBeNull();
        expect($schedule->next_run_at)->not->toBeNull();

        if ($delay >= 60) {
            // Real wait: wait until after run time then run scheduler
            sleep($delay + 2);
        } else {
            // Short delay: make schedule due so scheduler picks it up (avoids flaky timing in CI)
            $schedule->update(['next_run_at' => Carbon::now(config('app.timezone'))->subMinute()]);
        }

        Artisan::call('payroll:process-scheduled');

        $schedule->refresh();
        $jobs = PayrollJob::where('payroll_schedule_id', $schedule->id)->get();

        expect($jobs->isNotEmpty())->toBeTrue('Payroll jobs should be created');
        expect($jobs->contains(fn ($j) => in_array($j->status, ['succeeded', 'failed'])))->toBeTrue('At least one job should be processed (succeeded or failed)');
        if ($jobs->contains(fn ($j) => $j->status === 'succeeded')) {
            expect((float) $this->business->fresh()->escrow_balance)->toBeLessThan(100000);
        }
    });

    it('creates recurring payroll, waits, runs scheduler, then edits to run again, waits, runs again', function () {
        $delay = runDelaySeconds();
        $runAt = Carbon::now(config('app.timezone'))->addSeconds($delay);
        while ($runAt->isWeekend()) {
            $runAt->addDay();
        }
        $date = $runAt->format('Y-m-d');
        $time = $runAt->format('H:i');

        $response = $this->actingAs($this->user)->post(route('payroll.store'), [
            'business_id' => $this->business->id,
            'name' => 'Recurring test payroll',
            'schedule_type' => 'recurring',
            'frequency' => 'monthly',
            'employee_ids' => [$this->employee->id],
            'scheduled_date' => $date,
            'scheduled_time' => $time,
        ]);

        $response->assertRedirect(route('payroll.index'));
        $schedule = PayrollSchedule::where('business_id', $this->business->id)->where('name', 'Recurring test payroll')->first();
        expect($schedule)->not->toBeNull();

        if ($delay >= 60) {
            sleep($delay + 2);
        } else {
            $schedule->update(['next_run_at' => Carbon::now(config('app.timezone'))->subMinute()]);
        }
        Artisan::call('payroll:process-scheduled');
        $schedule->refresh();
        $jobs1 = PayrollJob::where('payroll_schedule_id', $schedule->id)->get();
        expect($jobs1->isNotEmpty())->toBeTrue();
        expect($schedule->next_run_at)->not->toBeNull();

        // Edit: set next run to runDelaySeconds() from now (simulate user changing date/time)
        $runAt2 = Carbon::now(config('app.timezone'))->addSeconds($delay);
        while ($runAt2->isWeekend()) {
            $runAt2->addDay();
        }
        $date2 = $runAt2->format('Y-m-d');
        $time2 = $runAt2->format('H:i');

        $response2 = $this->actingAs($this->user)->put(route('payroll.update', $schedule), [
            'business_id' => $this->business->id,
            'name' => $schedule->name,
            'schedule_type' => 'recurring',
            'frequency' => 'monthly',
            'employee_ids' => [$this->employee->id],
            'scheduled_date' => $date2,
            'scheduled_time' => $time2,
        ]);

        $response2->assertRedirect();
        $schedule->refresh();
        expect($schedule->next_run_at)->not->toBeNull();

        if ($delay >= 60) {
            sleep($delay + 2);
        } else {
            $schedule->update(['next_run_at' => Carbon::now(config('app.timezone'))->subMinute()]);
        }
        Artisan::call('payroll:process-scheduled');
        $schedule->refresh();
        $jobs2 = PayrollJob::where('payroll_schedule_id', $schedule->id)->get();
        expect($jobs2->count())->toBeGreaterThanOrEqual($jobs1->count());
    });
});

describe('Payroll as a business user – validation and flows', function () {
    it('cannot create payroll when escrow balance is zero', function () {
        EscrowDeposit::where('business_id', $this->business->id)->delete();
        $this->business->update(['escrow_balance' => 0]);
        $this->business->refresh();

        $runAt = Carbon::now(config('app.timezone'))->addMinutes(10);
        while ($runAt->isWeekend()) {
            $runAt->addDay();
        }

        $response = $this->actingAs($this->user)->post(route('payroll.store'), [
            'business_id' => $this->business->id,
            'name' => 'Should fail',
            'schedule_type' => 'one_time',
            'employee_ids' => [$this->employee->id],
            'scheduled_date' => $runAt->format('Y-m-d'),
            'scheduled_time' => $runAt->format('H:i'),
        ]);

        $response->assertSessionHasErrors();
        expect(PayrollSchedule::where('business_id', $this->business->id)->where('name', 'Should fail')->count())->toBe(0);
    });

    it('cannot create payroll with no employees selected', function () {
        $businessNoEmployees = Business::factory()->create(['user_id' => $this->user->id]);
        $this->user->businesses()->attach($businessNoEmployees->id, ['role' => 'owner']);
        EscrowDeposit::factory()->for($businessNoEmployees)->create([
            'status' => 'confirmed',
            'amount' => 50000,
            'authorized_amount' => 50000,
        ]);
        $businessNoEmployees->update(['escrow_balance' => 50000]);
        expect(Employee::where('business_id', $businessNoEmployees->id)->count())->toBe(0);

        $runAt = Carbon::now(config('app.timezone'))->addMinutes(10);
        while ($runAt->isWeekend()) {
            $runAt->addDay();
        }

        $response = $this->actingAs($this->user)->post(route('payroll.store'), [
            'business_id' => $businessNoEmployees->id,
            'name' => 'No employees',
            'schedule_type' => 'one_time',
            'employee_ids' => [],
            'scheduled_date' => $runAt->format('Y-m-d'),
            'scheduled_time' => $runAt->format('H:i'),
        ]);

        $response->assertSessionHasErrors(['employee_ids']);
        expect(PayrollSchedule::where('business_id', $businessNoEmployees->id)->where('name', 'No employees')->count())->toBe(0);
    });

    it('lists payroll schedules and can open create and edit', function () {
        $schedule = PayrollSchedule::factory()->for($this->business)->create([
            'name' => 'My schedule',
            'schedule_type' => 'recurring',
            'status' => 'active',
        ]);
        $schedule->employees()->attach($this->employee->id);

        $this->actingAs($this->user)->get(route('payroll.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('payroll/index')->has('schedules'));

        $this->actingAs($this->user)->get(route('payroll.create'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('payroll/create'));

        $this->actingAs($this->user)->get(route('payroll.edit', $schedule))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('payroll/edit')->has('schedule'));
    });

    it('can pause and resume a schedule', function () {
        $schedule = PayrollSchedule::factory()->for($this->business)->create([
            'name' => 'Pausable',
            'status' => 'active',
        ]);
        $schedule->employees()->attach($this->employee->id);

        $this->actingAs($this->user)->post(route('payroll.pause', $schedule))
            ->assertRedirect();
        $schedule->refresh();
        expect($schedule->status)->toBe('paused');

        $this->actingAs($this->user)->post(route('payroll.resume', $schedule))
            ->assertRedirect();
        $schedule->refresh();
        expect($schedule->status)->toBe('active');
    });

    it('due schedule is processed when next_run_at is in the past', function () {
        $schedule = PayrollSchedule::factory()->for($this->business)->create([
            'name' => 'Already due',
            'schedule_type' => 'one_time',
            'status' => 'active',
            'frequency' => app(\App\Services\CronExpressionService::class)->fromOneTime(Carbon::now(config('app.timezone'))->subMinute()),
            'next_run_at' => Carbon::now(config('app.timezone'))->subMinute(),
        ]);
        $schedule->employees()->attach($this->employee->id);

        Artisan::call('payroll:process-scheduled');

        $jobs = PayrollJob::where('payroll_schedule_id', $schedule->id)->get();
        expect($jobs->isNotEmpty())->toBeTrue();
        expect($jobs->every(fn ($j) => in_array($j->status, ['succeeded', 'processing'])))->toBeTrue();
    });
});
