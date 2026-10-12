<?php

namespace Database\Factories;

use App\Models\Table;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Table>
 */
class TableFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'number' => (string) fake()->unique()->numberBetween(1, 9999),
            'description' => fake()->optional()->words(3, true),
            'capacity' => 4,
            'code' => (string) fake()->unique()->numerify('##########'),
            'sort_order' => 0,
            'status' => 0,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    public function occupied(): static
    {
        return $this->state(['status' => 1]);
    }

    public function cleaning(): static
    {
        return $this->state(['status' => 3]);
    }
}
