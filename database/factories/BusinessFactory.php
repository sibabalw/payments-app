<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Business>
 */
class BusinessFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => \App\Models\User::factory(),
            'name' => fake()->company(),
            'business_type' => fake()->randomElement(['small_business', 'other']),
            'status' => 'active',
            'email' => fake()->companyEmail(),
            'phone' => fake()->phoneNumber(),
            'city' => fake()->city(),
            'country' => fake()->country(),
            'contact_person_name' => fake()->name(),
            'escrow_balance' => app()->environment('testing') ? 10000000.00 : 0.00,
            'bank_account_details' => null,
        ];
    }
}
