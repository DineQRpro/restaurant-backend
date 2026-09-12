<?php

namespace Tests\Feature\Menu;

use App\Enums\DietaryType;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithAuthorization;
use Tests\TestCase;

class MenuItemCrudTest extends TestCase
{
    use InteractsWithAuthorization, RefreshDatabase;

    private MenuCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAuthorization();
        Sanctum::actingAs($this->userWithRole('admin'));
        $this->category = MenuCategory::factory()->create(['name' => 'Main Course']);
    }

    public function test_it_creates_a_menu_item(): void
    {
        $response = $this->postJson('/api/v1/menu/items', $this->validPayload());

        $response->assertCreated()
            ->assertJson([
                'success' => true,
                'message' => 'Menu item created successfully.',
                'data' => [
                    'name' => 'Chicken Biriyani',
                    'price' => '180.00',
                    'is_available' => true,
                    'is_chef_special' => false,
                    'spicy_level' => 2,
                    'dietary_type' => 'non_vegetarian',
                    'category' => [
                        'id' => $this->category->id,
                        'name' => 'Main Course',
                    ],
                ],
            ]);

        $this->assertDatabaseHas('menu_items', [
            'name' => 'Chicken Biriyani',
            'category_id' => $this->category->id,
            'price' => '180.00',
        ]);
    }

    public function test_it_stores_decimal_prices_correctly(): void
    {
        foreach (['100', '100.50', '999.99'] as $price) {
            $response = $this->postJson('/api/v1/menu/items', $this->validPayload([
                'name' => 'Item '.$price,
                'price' => $price,
            ]));

            $response->assertCreated();
            $this->assertSame(
                number_format((float) $price, 2, '.', ''),
                $response->json('data.price'),
            );
        }
    }

    public function test_it_rejects_invalid_item_payloads(): void
    {
        $this->postJson('/api/v1/menu/items', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'category_id', 'dietary_type', 'price']);

        $this->postJson('/api/v1/menu/items', $this->validPayload([
            'category_id' => 9999,
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['category_id']);

        $this->postJson('/api/v1/menu/items', $this->validPayload([
            'price' => -1,
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['price']);

        $this->postJson('/api/v1/menu/items', $this->validPayload([
            'price' => '12.345',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['price']);

        $this->postJson('/api/v1/menu/items', $this->validPayload([
            'spicy_level' => 5,
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['spicy_level']);
    }

    public function test_it_rejects_inactive_categories_for_new_items(): void
    {
        $inactive = MenuCategory::factory()->inactive()->create();

        $this->postJson('/api/v1/menu/items', $this->validPayload([
            'category_id' => $inactive->id,
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['category_id']);
    }

    public function test_it_shows_updates_and_toggles_availability(): void
    {
        $item = MenuItem::factory()->create([
            'name' => 'Old Item',
            'category_id' => $this->category->id,
            'price' => '50.00',
        ]);

        $this->getJson('/api/v1/menu/items/'.$item->id)
            ->assertOk()
            ->assertJsonPath('data.name', 'Old Item')
            ->assertJsonPath('data.category.name', 'Main Course');

        $this->putJson('/api/v1/menu/items/'.$item->id, $this->validPayload([
            'name' => 'Updated Item',
            'price' => '75.50',
            'is_chef_special' => true,
        ]))
            ->assertOk()
            ->assertJsonPath('data.name', 'Updated Item')
            ->assertJsonPath('data.price', '75.50')
            ->assertJsonPath('data.is_chef_special', true);

        $this->patchJson('/api/v1/menu/items/'.$item->id.'/status', [
            'is_available' => false,
        ])
            ->assertOk()
            ->assertJsonPath('data.is_available', false);

        $this->assertFalse($item->fresh()->is_available);
        $this->assertDatabaseHas('menu_items', ['id' => $item->id]);
    }

    public function test_it_deletes_a_menu_item(): void
    {
        $item = MenuItem::factory()->create(['category_id' => $this->category->id]);

        $this->deleteJson('/api/v1/menu/items/'.$item->id)
            ->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Menu item deleted successfully.',
            ]);

        $this->assertDatabaseMissing('menu_items', ['id' => $item->id]);
    }

    public function test_unknown_item_returns_not_found(): void
    {
        $this->getJson('/api/v1/menu/items/9999')->assertNotFound();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Chicken Biriyani',
            'description' => 'Fragrant rice',
            'category_id' => $this->category->id,
            'dietary_type' => DietaryType::NonVegetarian->value,
            'price' => '180.00',
            'spicy_level' => 2,
        ], $overrides);
    }
}
