<?php

namespace Tests\Feature\Menu;

use App\Models\MenuItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithAuthorization;
use Tests\TestCase;

class MenuItemAuthorizationTest extends TestCase
{
    use InteractsWithAuthorization, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAuthorization();
    }

    public function test_unauthenticated_user_cannot_list_items(): void
    {
        $this->getJson('/api/v1/menu/items')->assertUnauthorized();
    }

    public function test_waiter_can_list_items(): void
    {
        Sanctum::actingAs($this->userWithRole('waiter'));

        $this->getJson('/api/v1/menu/items')
            ->assertOk()
            ->assertJson(['success' => true]);
    }

    public function test_admin_can_list_items(): void
    {
        Sanctum::actingAs($this->userWithRole('admin'));

        $this->getJson('/api/v1/menu/items')
            ->assertOk()
            ->assertJson(['success' => true]);
    }

    public function test_waiter_cannot_create_items(): void
    {
        Sanctum::actingAs($this->userWithRole('waiter'));

        $this->postJson('/api/v1/menu/items', [])
            ->assertForbidden();
    }

    public function test_waiter_cannot_update_or_delete_items(): void
    {
        $item = MenuItem::factory()->create();
        $waiter = $this->userWithRole('waiter');

        Sanctum::actingAs($waiter);

        $this->putJson('/api/v1/menu/items/'.$item->id, [])
            ->assertForbidden();

        $this->patchJson('/api/v1/menu/items/'.$item->id.'/status', ['is_available' => false])
            ->assertForbidden();

        $this->deleteJson('/api/v1/menu/items/'.$item->id)
            ->assertForbidden();
    }
}
