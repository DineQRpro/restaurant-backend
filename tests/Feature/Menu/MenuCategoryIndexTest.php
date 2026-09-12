<?php

namespace Tests\Feature\Menu;

use App\Models\MenuCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithAuthorization;
use Tests\TestCase;

class MenuCategoryIndexTest extends TestCase
{
    use InteractsWithAuthorization, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAuthorization();
        Sanctum::actingAs($this->userWithRole('admin'));
    }

    public function test_it_lists_categories(): void
    {
        MenuCategory::factory()->create(['name' => 'Main Course']);

        $this->getJson('/api/v1/menu/categories')
            ->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Menu categories retrieved successfully.',
            ])
            ->assertJsonPath('data.0.name', 'Main Course')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'description',
                        'image',
                        'sort_order',
                        'is_active',
                        'created_at',
                        'updated_at',
                    ],
                ],
                'meta' => ['current_page', 'per_page', 'total', 'last_page'],
            ]);
    }

    public function test_it_searches_and_filters_categories(): void
    {
        MenuCategory::factory()->create(['name' => 'Starters', 'is_active' => true]);
        MenuCategory::factory()->inactive()->create(['name' => 'Archived']);

        $this->getJson('/api/v1/menu/categories?search=start')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Starters');

        $inactive = collect($this->getJson('/api/v1/menu/categories?is_active=0')->json('data'))
            ->pluck('name');

        $this->assertTrue($inactive->contains('Archived'));
        $this->assertFalse($inactive->contains('Starters'));
    }

    public function test_it_paginates_categories(): void
    {
        MenuCategory::factory()->count(25)->create();

        $this->getJson('/api/v1/menu/categories?per_page=10')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 10)
            ->assertJsonCount(10, 'data');
    }
}
