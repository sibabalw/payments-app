<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ErrorLog>
 */
class ErrorLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => null,
            'type' => fake()->randomElement(['error', 'exception', 'warning']),
            'level' => fake()->randomElement(['error', 'warning', 'critical', 'info']),
            'message' => fake()->sentence(),
            'exception' => fake()->optional()->randomElement([
                'Illuminate\Database\QueryException',
                'Symfony\Component\HttpKernel\Exception\NotFoundHttpException',
                'Illuminate\Validation\ValidationException',
            ]),
            'trace' => fake()->optional()->text(500),
            'file' => fake()->filePath(),
            'line' => fake()->numberBetween(1, 1000),
            'url' => fake()->url(),
            'method' => fake()->randomElement(['GET', 'POST', 'PUT', 'PATCH', 'DELETE']),
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'context' => [],
            'is_admin_error' => false,
            'notified' => false,
            'notified_at' => null,
        ];
    }
}
