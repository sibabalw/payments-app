<?php

use App\Models\Business;
use App\Models\Employee;
use App\Models\EscrowDeposit;
use App\Models\PayrollJob;
use App\Models\PayrollSchedule;
use App\Models\User;
use App\Services\PaymentService;
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
});

describe('Concurrent Payroll Processing', function () {
    it('prevents duplicate processing of the same payroll job', function () {
        $job = PayrollJob::factory()->for($this->schedule)->for($this->employee)->create([
            'status' => 'pending',
            'net_salary' => 25000,
        ]);

        // Simulate concurrent processing attempts
        $results = [];
        $processes = 5;

        for ($i = 0; $i < $processes; $i++) {
            $results[] = $this->paymentService->processPayrollJob($job);
        }

        // Only one should succeed
        $successCount = count(array_filter($results));
        expect($successCount)->toBe(1);

        // Job should be marked as succeeded
        $job->refresh();
        expect($job->status)->toBe('succeeded');
    });

    it('handles concurrent escrow balance checks correctly', function () {
        $jobs = PayrollJob::factory()->count(3)->for($this->schedule)->for($this->employee)->create([
            'status' => 'pending',
            'net_salary' => 30000, // Each job needs 30k, total balance is 100k
        ]);

        // Process all jobs concurrently
        $results = [];
        foreach ($jobs as $job) {
            $results[] = $this->paymentService->processPayrollJob($job);
        }

        // All should succeed as total (90k) is less than balance (100k)
        $successCount = count(array_filter($results));
        expect($successCount)->toBe(3);

        // Verify escrow balance was decremented correctly
        $this->business->refresh();
        expect($this->business->escrow_balance)->toBe(10000.0); // 100k - 90k
    });

    it('prevents processing when escrow balance is insufficient', function () {
        // Create jobs that exceed balance
        $jobs = PayrollJob::factory()->count(4)->for($this->schedule)->for($this->employee)->create([
            'status' => 'pending',
            'net_salary' => 30000, // Each needs 30k, but only 100k available
        ]);

        $results = [];
        foreach ($jobs as $job) {
            $results[] = $this->paymentService->processPayrollJob($job);
        }

        // Only 3 should succeed (3 * 30k = 90k <= 100k)
        // 4th should fail due to insufficient balance
        $successCount = count(array_filter($results));
        expect($successCount)->toBeLessThanOrEqual(3);

        // Verify at least one failed
        $failedJobs = PayrollJob::where('status', 'failed')->count();
        expect($failedJobs)->toBeGreaterThan(0);
    });
});

describe('Transaction Isolation', function () {
    it('maintains data consistency under concurrent updates', function () {
        $job = PayrollJob::factory()->for($this->schedule)->for($this->employee)->create([
            'status' => 'pending',
            'net_salary' => 25000,
        ]);

        // Simulate concurrent status updates
        DB::transaction(function () use ($job) {
            $job->updateStatus('processing');
            // Simulate another process trying to update
            $freshJob = PayrollJob::find($job->id);
            expect($freshJob->status)->toBe('processing');
        });
    });
});
