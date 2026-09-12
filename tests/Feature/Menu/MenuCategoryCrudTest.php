<?php

namespace Tests\Feature\Menu;

use App\Models\MenuCategory;
use App\Models\MenuItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithAuthorization;
use Tests\TestCase;

class MenuCategoryCrudTest extends TestCase
{
    use InteractsWithAuthorization, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAuthorization();
        Sanctum::actingAs($this->userWithRole('admin'));
    }

    public function test_it_creates_a_category(): void
    {
        $response = $this->postJson('/api/v1/menu/categories', [
            'name' => 'Main Course',
            'description' => 'Hot dishes',
            'image' => 'categories/main.jpg',
            'sort_order' => 1,
        ]);

        $response->assertCreated()
            ->assertJson([
                'success' => true,
                'message' => 'Menu category created successfully.',
                'data' => [
                    'name' => 'Main Course',
                    'description' => 'Hot dishes',
                    'image' => 'categories/main.jpg',
                    'sort_order' => 1,
                    'is_active' => true,
                ],
            ]);

        $this->assertDatabaseHas('menu_categories', [
            'name' => 'Main Course',
            'is_active' => true,
        ]);
    }

    public function test_it_rejects_invalid_category_payloads(): void
    {
        $this->postJson('/api/v1/menu/categories', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);

        $this->postJson('/api/v1/menu/categories', [
            'name' => 'Sides',
            'sort_order' => -1,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sort_order']);
    }

    public function test_it_allows_duplicate_category_names(): void
    {
        MenuCategory::factory()->create(['name' => 'Drinks']);

        $this->postJson('/api/v1/menu/categories', ['name' => 'Drinks'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Drinks');

        $this->assertEquals(2, MenuCategory::where('name', 'Drinks')->count());
    }

    public function test_it_shows_and_updates_a_category(): void
    {
        $category = MenuCategory::factory()->create(['name' => 'Old Name']);

        $this->getJson('/api/v1/menu/categories/'.$category->id)
            ->assertOk()
            ->assertJsonPath('data.name', 'Old Name');

        $this->putJson('/api/v1/menu/categories/'.$category->id, [
            'name' => 'New Name',
            'description' => 'Updated',
            'sort_order' => 5,
            'is_active' => true,
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'New Name')
            ->assertJsonPath('data.sort_order', 5);
    }

    public function test_it_updates_category_status(): void
    {
        $category = MenuCategory::factory()->create(['is_active' => true]);

        $this->patchJson('/api/v1/menu/categories/'.$category->id.'/status', [
            'is_active' => false,
        ])
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->assertFalse($category->fresh()->is_active);
    }

    public function test_it_deletes_a_category_without_items(): void
    {
        $category = MenuCategory::factory()->create();

        $this->deleteJson('/api/v1/menu/categories/'.$category->id)
            ->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Menu category deleted successfully.',
            ]);

        $this->assertDatabaseMissing('menu_categories', ['id' => $category->id]);
    }

    public function test_it_does_not_delete_a_category_that_has_items(): void
    {
        $category = MenuCategory::factory()->create();
        MenuItem::factory()->create(['category_id' => $category->id]);

        $this->deleteJson('/api/v1/menu/categories/'.$category->id)
            ->assertConflict()
            ->assertJson([
                'success' => false,
                'message' => 'This category cannot be deleted because it still has menu items. Deactivate it or move the items first.',
            ]);

        $this->assertDatabaseHas('menu_categories', ['id' => $category->id]);
        $this->assertEquals(1, $category->items()->count());
    }

    public function test_unknown_category_returns_not_found(): void
    {
        $this->getJson('/api/v1/menu/categories/9999')
            ->assertNotFound();
    }
}
