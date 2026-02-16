<?php

namespace Database\Factories;

use App\Models\Business;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ComplianceSubmission>
 */
class ComplianceSubmissionFactory extends Factory
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
            'employee_id' => null,
            'type' => fake()->randomElement(['ui19', 'emp201', 'irp5']),
            'period' => fake()->date('Y-m'),
            'status' => 'generated',
            'data' => [],
            'file_path' => null,
            'submitted_at' => null,
        ];
    }

    /**
     * Configure as UI-19 submission.
     */
    public function ui19(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'ui19',
            'data' => [
                'business' => [
                    'name' => 'Test Business',
                    'registration_number' => '2024/123456/07',
                    'uif_reference' => 'U123456789',
                ],
                'period' => '2026-01',
                'period_display' => 'January 2026',
                'employees' => [],
                'totals' => [
                    'total_employees' => 0,
                    'total_gross_remuneration' => 0,
                    'total_uif_employee' => 0,
                    'total_uif_employer' => 0,
                    'total_uif_contribution' => 0,
                ],
            ],
        ]);
    }

    /**
     * Configure as EMP201 submission.
     */
    public function emp201(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'emp201',
            'data' => [
                'business' => [
                    'name' => 'Test Business',
                    'paye_reference' => '7000123456',
                ],
                'period' => '2026-01',
                'totals' => [
                    'total_paye' => 0,
                    'total_uif' => 0,
                    'total_sdl' => 0,
                    'total_liability' => 0,
                ],
            ],
        ]);
    }

    /**
     * Configure as IRP5 submission.
     */
    public function irp5(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'irp5',
            'period' => '2025/2026',
            'employee_id' => Employee::factory(),
            'data' => [
                'certificate_number' => 'IRP5-20252026-0001-000001',
                'tax_year' => '2025/2026',
                'employee' => [
                    'name' => 'Test Employee',
                    'id_number' => '8501015009087',
                    'tax_number' => '123456789',
                ],
                'income' => [
                    'sources' => [
                        ['code' => '3601', 'description' => 'Gross Remuneration', 'amount' => 600000],
                    ],
                    'total' => 600000,
                ],
                'deductions' => [
                    'items' => [
                        ['code' => '4102', 'description' => 'PAYE', 'amount' => 108000],
                        ['code' => '4141', 'description' => 'UIF', 'amount' => 2125.44],
                    ],
                    'total' => 110125.44,
                ],
            ],
        ]);
    }

    /**
     * Mark as submitted.
     */
    public function submitted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);
    }

    /**
     * Mark as draft.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'draft',
        ]);
    }
}
