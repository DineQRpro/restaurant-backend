<?php

namespace Tests\Feature\Menu;

use App\Models\MenuCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithAuthorization;
use Tests\TestCase;

class MenuCategoryAuthorizationTest extends TestCase
{
    use InteractsWithAuthorization, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAuthorization();
    }

    public function test_unauthenticated_user_cannot_list_categories(): void
    {
        $this->getJson('/api/v1/menu/categories')->assertUnauthorized();
    }

    public function test_waiter_can_list_categories(): void
    {
        Sanctum::actingAs($this->userWithRole('waiter'));

        $this->getJson('/api/v1/menu/categories')
            ->assertOk()
            ->assertJson(['success' => true]);
    }

    public function test_admin_can_list_categories(): void
    {
        Sanctum::actingAs($this->userWithRole('admin'));

        $this->getJson('/api/v1/menu/categories')
            ->assertOk()
            ->assertJson(['success' => true]);
    }

    public function test_waiter_cannot_create_categories(): void
    {
        Sanctum::actingAs($this->userWithRole('waiter'));

        $this->postJson('/api/v1/menu/categories', ['name' => 'Starters'])
            ->assertForbidden();
    }

    public function test_waiter_cannot_update_categories(): void
    {
        $category = MenuCategory::factory()->create();

        Sanctum::actingAs($this->userWithRole('waiter'));

        $this->putJson('/api/v1/menu/categories/'.$category->id, ['name' => 'Updated'])
            ->assertForbidden();
    }

    public function test_waiter_cannot_delete_categories(): void
    {
        $category = MenuCategory::factory()->create();

        Sanctum::actingAs($this->userWithRole('waiter'));

        $this->deleteJson('/api/v1/menu/categories/'.$category->id)
            ->assertForbidden();
    }
}
