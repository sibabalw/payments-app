<?php

namespace Database\Factories;

use App\Models\Business;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Employee>
 */
class EmployeeFactory extends Factory
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
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'id_number' => fake()->numerify('#############'),
            'tax_number' => fake()->numerify('#########'),
            'employment_type' => fake()->randomElement(['full_time', 'part_time', 'contract']),
            'department' => fake()->randomElement(['Engineering', 'Sales', 'Marketing', 'Operations', 'Finance']),
            'start_date' => fake()->dateTimeBetween('-2 years', '-1 month'),
            'gross_salary' => fake()->randomFloat(2, 15000, 80000),
            'hours_worked_per_month' => 160,
            'hourly_rate' => null,
            'overtime_rate_multiplier' => 1.5,
            'weekend_rate_multiplier' => 2.0,
            'holiday_rate_multiplier' => 2.5,
            'bank_account_details' => [
                'bank_name' => fake()->randomElement(['FNB', 'Standard Bank', 'Absa', 'Nedbank', 'Capitec']),
                'account_number' => fake()->numerify('##########'),
                'branch_code' => fake()->numerify('######'),
                'account_type' => fake()->randomElement(['savings', 'current']),
            ],
            'tax_status' => 'normal',
            'notes' => null,
        ];
    }

    /**
     * Indicate that the employee is part-time.
     */
    public function partTime(): static
    {
        return $this->state(fn (array $attributes) => [
            'employment_type' => 'part_time',
            'hours_worked_per_month' => fake()->randomFloat(0, 20, 80),
            'gross_salary' => fake()->randomFloat(2, 5000, 20000),
        ]);
    }

    /**
     * Indicate that the employee works fewer than 24 hours/month (UIF exempt).
     */
    public function uifExempt(): static
    {
        return $this->state(fn (array $attributes) => [
            'employment_type' => 'part_time',
            'hours_worked_per_month' => fake()->randomFloat(0, 10, 23),
        ]);
    }
}
