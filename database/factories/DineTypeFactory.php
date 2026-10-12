<?php

namespace Database\Factories;

use App\Models\DineType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<DineType>
 */
class DineTypeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => Str::title($name),
            'slug' => Str::slug($name).'-'.fake()->unique()->numerify('##'),
            'description' => null,
            'sort_order' => 1,
            'is_active' => true,
            'requires_full_table' => false,
        ];
    }

    public function takeAway(): static
    {
        return $this->state([
            'name' => 'Take Away',
            'slug' => DineType::SLUG_TAKE_AWAY,
        ]);
    }

    public function dineIn(): static
    {
        return $this->state([
            'name' => 'Dine In',
            'slug' => 'dine-in',
            'requires_full_table' => false,
        ]);
    }

    public function coupleDine(): static
    {
        return $this->state([
            'name' => 'Couple Dine',
            'slug' => 'couple-dine',
            'requires_full_table' => true,
        ]);
    }

    public function candleLightDinner(): static
    {
        return $this->state([
            'name' => 'Candle Light Dinner',
            'slug' => 'candle-light-dinner',
            'requires_full_table' => true,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
