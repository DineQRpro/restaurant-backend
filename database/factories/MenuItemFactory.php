<?php

namespace Database\Factories;

use App\Enums\DietaryType;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MenuItem>
 */
class MenuItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(3, true),
            'description' => fake()->optional()->sentence(),
            'category_id' => MenuCategory::factory(),
            'dietary_type' => DietaryType::Vegetarian,
            'price' => fake()->randomFloat(2, 10, 999),
            'is_available' => true,
            'is_chef_special' => false,
            'sort_order' => 0,
            'spicy_level' => null,
        ];
    }

    public function unavailable(): static
    {
        return $this->state(['is_available' => false]);
    }

    public function chefSpecial(): static
    {
        return $this->state(['is_chef_special' => true]);
    }
}
