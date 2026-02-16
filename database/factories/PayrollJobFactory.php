<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\PayrollSchedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PayrollJob>
 */
class PayrollJobFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $grossSalary = fake()->randomFloat(2, 15000, 80000);
        $paye = round($grossSalary * 0.18, 2); // Simplified PAYE
        $uif = round(min($grossSalary, 17712) * 0.01, 2);
        $sdl = round($grossSalary * 0.01, 2);

        return [
            'payroll_schedule_id' => PayrollSchedule::factory(),
            'employee_id' => Employee::factory(),
            'gross_salary' => $grossSalary,
            'paye_amount' => $paye,
            'uif_amount' => $uif,
            'sdl_amount' => $sdl,
            'adjustments' => [],
            'net_salary' => $grossSalary - $paye - $uif,
            'currency' => 'ZAR',
            'status' => 'succeeded',
            'error_message' => null,
            'processed_at' => fake()->dateTimeBetween('-1 month', 'now'),
            'transaction_id' => fake()->uuid(),
            'fee' => round($grossSalary * 0.015, 2),
            'escrow_deposit_id' => null,
            'pay_period_start' => fake()->dateTimeBetween('-2 months', '-1 month'),
            'pay_period_end' => fake()->dateTimeBetween('-1 month', 'now'),
        ];
    }

    /**
     * Indicate that the job failed.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'error_message' => 'Insufficient funds in escrow account',
            'processed_at' => null,
            'transaction_id' => null,
        ]);
    }

    /**
     * Indicate that the job is pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'processed_at' => null,
            'transaction_id' => null,
        ]);
    }

    /**
     * Indicate that the job succeeded.
     */
    public function succeeded(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'succeeded',
            'error_message' => null,
            'processed_at' => now(),
            'transaction_id' => \Illuminate\Support\Str::uuid(),
        ]);
    }

    /**
     * Set the pay period to a specific month.
     */
    public function forMonth(int $year, int $month): static
    {
        $start = \Carbon\Carbon::create($year, $month, 1)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        return $this->state(fn (array $attributes) => [
            'pay_period_start' => $start,
            'pay_period_end' => $end,
            'processed_at' => $end,
        ]);
    }

    /**
     * Add adjustments.
     */
    public function withAdjustments(array $adjustments = []): static
    {
        $defaultAdjustments = [
            [
                'name' => 'Medical Aid',
                'type' => 'fixed',
                'adjustment_type' => 'deduction',
                'amount' => 1500,
                'original_amount' => 1500,
                'is_recurring' => true,
            ],
            [
                'name' => 'Pension Fund',
                'type' => 'percentage',
                'adjustment_type' => 'deduction',
                'amount' => 5,
                'original_amount' => 5,
                'is_recurring' => true,
            ],
        ];

        return $this->state(fn (array $attributes) => [
            'adjustments' => empty($adjustments) ? $defaultAdjustments : $adjustments,
        ]);
    }
}
