<?php

namespace Database\Factories;

use App\Models\Business;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EscrowDeposit>
 */
class EscrowDepositFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $amount = fake()->randomFloat(2, 1000, 100000);

        return [
            'business_id' => Business::factory(),
            'amount' => $amount,
            'fee_amount' => 0,
            'authorized_amount' => $amount,
            'currency' => 'ZAR',
            'status' => 'confirmed',
            'entry_method' => 'app',
            'entered_by' => null,
            'bank_reference' => fake()->optional()->regexify('[A-Z0-9]{10,20}'),
            'deposited_at' => now(),
            'completed_at' => now(),
        ];
    }
}
