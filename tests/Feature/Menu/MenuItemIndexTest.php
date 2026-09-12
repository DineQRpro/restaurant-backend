<?php

namespace Tests\Feature\Menu;

use App\Enums\DietaryType;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithAuthorization;
use Tests\TestCase;

class MenuItemIndexTest extends TestCase
{
    use InteractsWithAuthorization, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAuthorization();
        Sanctum::actingAs($this->userWithRole('admin'));
    }

    public function test_it_lists_items_with_category(): void
    {
        $category = MenuCategory::factory()->create(['name' => 'Main Course']);
        MenuItem::factory()->create([
            'name' => 'Chicken Biriyani',
            'category_id' => $category->id,
            'price' => '180.00',
        ]);

        $this->getJson('/api/v1/menu/items')
            ->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Menu items retrieved successfully.',
            ])
            ->assertJsonPath('data.0.name', 'Chicken Biriyani')
            ->assertJsonPath('data.0.price', '180.00')
            ->assertJsonPath('data.0.category.name', 'Main Course')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'price',
                        'is_available',
                        'is_chef_special',
                        'sort_order',
                        'spicy_level',
                        'dietary_type',
                        'category' => ['id', 'name'],
                    ],
                ],
                'meta' => ['current_page', 'per_page', 'total', 'last_page'],
            ]);
    }

    public function test_it_searches_and_filters_items(): void
    {
        $mains = MenuCategory::factory()->create(['name' => 'Mains']);
        $drinks = MenuCategory::factory()->create(['name' => 'Drinks']);

        MenuItem::factory()->create([
            'name' => 'Chicken Biriyani',
            'category_id' => $mains->id,
            'dietary_type' => DietaryType::NonVegetarian,
            'is_available' => true,
            'is_chef_special' => true,
        ]);
        MenuItem::factory()->unavailable()->create([
            'name' => 'Mango Lassi',
            'category_id' => $drinks->id,
            'dietary_type' => DietaryType::Vegetarian,
            'is_chef_special' => false,
        ]);

        $this->getJson('/api/v1/menu/items?search=chicken')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Chicken Biriyani');

        $this->getJson('/api/v1/menu/items?category_id='.$drinks->id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Mango Lassi');

        $this->getJson('/api/v1/menu/items?dietary_type=non_vegetarian')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Chicken Biriyani');

        $this->getJson('/api/v1/menu/items?is_available=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Chicken Biriyani');

        $this->getJson('/api/v1/menu/items?is_chef_special=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Chicken Biriyani');
    }

    public function test_it_paginates_and_sorts_items(): void
    {
        $category = MenuCategory::factory()->create();
        MenuItem::factory()->count(25)->create(['category_id' => $category->id]);

        $this->getJson('/api/v1/menu/items?per_page=10&page=1&sort=name&order=asc')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 10)
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonCount(10, 'data');
    }

    public function test_it_rejects_invalid_filters(): void
    {
        $this->getJson('/api/v1/menu/items?category_id=9999')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['category_id']);

        $this->getJson('/api/v1/menu/items?dietary_type=vegan')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['dietary_type']);
    }
}
