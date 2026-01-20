<?php

namespace Database\Factories;

use App\Models\Business;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PayrollSchedule>
 */
class PayrollScheduleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'name' => fake()->words(3, true).' Payroll',
            'frequency' => fake()->randomElement(['weekly', 'bi-weekly', 'monthly']),
            'schedule_type' => 'recurring',
            'status' => 'active',
            'next_run_at' => fake()->dateTimeBetween('now', '+1 month'),
            'last_run_at' => fake()->dateTimeBetween('-1 month', 'now'),
        ];
    }

    /**
     * Indicate that the schedule is one-time.
     */
    public function oneTime(): static
    {
        return $this->state(fn (array $attributes) => [
            'schedule_type' => 'one_time',
            'frequency' => 'one_time',
        ]);
    }

    /**
     * Indicate that the schedule is paused.
     */
    public function paused(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'paused',
        ]);
    }
}
