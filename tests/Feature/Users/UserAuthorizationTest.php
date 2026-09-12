<?php

namespace Tests\Feature\Users;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithAuthorization;
use Tests\TestCase;

class UserAuthorizationTest extends TestCase
{
    use InteractsWithAuthorization, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAuthorization();
    }

    public function test_unauthenticated_user_cannot_list_users(): void
    {
        $this->getJson('/api/v1/users')->assertUnauthorized();
    }

    public function test_waiter_cannot_list_users(): void
    {
        Sanctum::actingAs($this->userWithRole('waiter'));

        $this->getJson('/api/v1/users')
            ->assertForbidden()
            ->assertJson([
                'success' => false,
                'message' => 'You do not have permission to perform this action.',
            ]);
    }

    public function test_admin_can_list_users(): void
    {
        Sanctum::actingAs($this->userWithRole('admin'));

        $this->getJson('/api/v1/users')
            ->assertOk()
            ->assertJson(['success' => true]);
    }

    public function test_waiter_cannot_create_users(): void
    {
        Sanctum::actingAs($this->userWithRole('waiter'));

        $this->postJson('/api/v1/users', [])
            ->assertForbidden();
    }

    public function test_waiter_cannot_update_users(): void
    {
        $waiter = $this->userWithRole('waiter');
        $target = $this->userWithRole('counter');

        Sanctum::actingAs($waiter);

        $this->putJson('/api/v1/users/'.$target->id, [])
            ->assertForbidden();
    }

    public function test_waiter_cannot_update_user_status(): void
    {
        $waiter = $this->userWithRole('waiter');
        $target = $this->userWithRole('counter');

        Sanctum::actingAs($waiter);

        $this->patchJson('/api/v1/users/'.$target->id.'/status', ['is_active' => false])
            ->assertForbidden();
    }

    public function test_waiter_cannot_delete_users(): void
    {
        $waiter = $this->userWithRole('waiter');
        $target = $this->userWithRole('counter');

        Sanctum::actingAs($waiter);

        $this->deleteJson('/api/v1/users/'.$target->id)
            ->assertForbidden();
    }
}
