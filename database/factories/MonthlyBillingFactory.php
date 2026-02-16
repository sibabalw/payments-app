<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MonthlyBilling>
 */
class MonthlyBillingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id' => \App\Models\Business::factory(),
            'billing_month' => now()->subMonth()->format('Y-m'),
            'business_type' => 'small_business',
            'subscription_fee' => 1000.00,
            'total_deposit_fees' => 0.00,
            'status' => 'pending',
            'billed_at' => now(),
            'paid_at' => null,
        ];
    }
}
