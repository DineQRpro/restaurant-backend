<?php

namespace Database\Factories;

use App\Models\MenuCategory;
use App\Models\MenuType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MenuCategory>
 */
class MenuCategoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'menu_type_id' => MenuType::query()->firstOrCreate(
                ['slug' => MenuType::SLUG_FOOD],
                ['name' => 'Food Menu', 'sort_order' => 1, 'is_active' => true],
            )->id,
            'name' => fake()->unique()->words(2, true),
            'description' => fake()->optional()->sentence(),
            'image' => null,
            'sort_order' => 0,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
