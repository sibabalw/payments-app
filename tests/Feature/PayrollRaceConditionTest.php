<?php

use App\Models\Business;
use App\Models\Employee;
use App\Models\PayrollJob;
use App\Models\PayrollSchedule;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->business = Business::factory()->create([
        'user_id' => $this->user->id,
    ]);
    $this->user->businesses()->attach($this->business->id, ['role' => 'owner']);

    $this->employee = Employee::factory()->for($this->business)->create();
    $this->schedule = PayrollSchedule::factory()->for($this->business)->create([
        'status' => 'active',
        'next_run_at' => now()->subMinute(),
        'last_run_at' => null,
    ]);
    $this->schedule->employees()->attach($this->employee->id);
});

describe('Schedule Processing Race Conditions', function () {
    it('prevents duplicate schedule processing', function () {
        $periodStart = Carbon::parse('2026-01-01');
        $periodEnd = Carbon::parse('2026-01-31');

        // Simulate two processes trying to process the same schedule
        $process1 = function () {
            return DB::transaction(function () {
                $schedule = PayrollSchedule::where('id', $this->schedule->id)
                    ->lockForUpdate()
                    ->first();

                if ($schedule->last_run_at !== null && $schedule->last_run_at >= $schedule->next_run_at) {
                    return 0;
                }

                $schedule->update(['last_run_at' => now()]);

                return 1;
            });
        };

        $process2 = function () {
            return DB::transaction(function () {
                $schedule = PayrollSchedule::where('id', $this->schedule->id)
                    ->lockForUpdate()
                    ->first();

                if ($schedule->last_run_at !== null && $schedule->last_run_at >= $schedule->next_run_at) {
                    return 0;
                }

                $schedule->update(['last_run_at' => now()]);

                return 1;
            });
        };

        // Run both processes
        $result1 = $process1();
        $result2 = $process2();

        // Only one should process the schedule
        expect($result1 + $result2)->toBe(1);
    });
});

describe('Period Overlap Prevention', function () {
    it('prevents creating overlapping payroll jobs', function () {
        $periodStart = Carbon::parse('2026-01-01');
        $periodEnd = Carbon::parse('2026-01-31');

        // Create first job
        $job1 = PayrollJob::factory()->for($this->schedule)->for($this->employee)->create([
            'pay_period_start' => $periodStart,
            'pay_period_end' => $periodEnd,
            'status' => 'succeeded',
        ]);

        // Try to create overlapping job - should fail at database level
        expect(function () use ($periodStart, $periodEnd) {
            PayrollJob::factory()->for($this->schedule)->for($this->employee)->create([
                'pay_period_start' => $periodStart->copy()->addDays(15), // Overlaps
                'pay_period_end' => $periodEnd->copy()->addDays(15), // Overlaps
                'status' => 'pending',
            ]);
        })->toThrow(\Exception::class);
    });

    it('allows non-overlapping periods', function () {
        $period1Start = Carbon::parse('2026-01-01');
        $period1End = Carbon::parse('2026-01-31');
        $period2Start = Carbon::parse('2026-02-01');
        $period2End = Carbon::parse('2026-02-28');

        $job1 = PayrollJob::factory()->for($this->schedule)->for($this->employee)->create([
            'pay_period_start' => $period1Start,
            'pay_period_end' => $period1End,
            'status' => 'succeeded',
        ]);

        $job2 = PayrollJob::factory()->for($this->schedule)->for($this->employee)->create([
            'pay_period_start' => $period2Start,
            'pay_period_end' => $period2End,
            'status' => 'pending',
        ]);

        expect($job2->id)->toBeGreaterThan(0);
    });
});

describe('Unique Constraint Enforcement', function () {
    it('prevents duplicate active jobs for same employee and period', function () {
        $periodStart = Carbon::parse('2026-01-01');
        $periodEnd = Carbon::parse('2026-01-31');

        // Create first pending job
        $job1 = PayrollJob::factory()->for($this->schedule)->for($this->employee)->create([
            'pay_period_start' => $periodStart,
            'pay_period_end' => $periodEnd,
            'status' => 'pending',
        ]);

        // Try to create duplicate - should fail
        expect(function () use ($periodStart, $periodEnd) {
            PayrollJob::factory()->for($this->schedule)->for($this->employee)->create([
                'pay_period_start' => $periodStart,
                'pay_period_end' => $periodEnd,
                'status' => 'pending',
            ]);
        })->toThrow(\Exception::class);
    });

    it('allows duplicate if one is failed', function () {
        $periodStart = Carbon::parse('2026-01-01');
        $periodEnd = Carbon::parse('2026-01-31');

        // Create failed job
        $job1 = PayrollJob::factory()->for($this->schedule)->for($this->employee)->create([
            'pay_period_start' => $periodStart,
            'pay_period_end' => $periodEnd,
            'status' => 'failed',
        ]);

        // Should be able to create new pending job
        $job2 = PayrollJob::factory()->for($this->schedule)->for($this->employee)->create([
            'pay_period_start' => $periodStart,
            'pay_period_end' => $periodEnd,
            'status' => 'pending',
        ]);

        expect($job2->id)->toBeGreaterThan(0);
    });
});
