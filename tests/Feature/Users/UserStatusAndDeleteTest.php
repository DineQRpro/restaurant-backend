<?php

namespace Tests\Feature\Users;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithAuthorization;
use Tests\TestCase;

class UserStatusAndDeleteTest extends TestCase
{
    use InteractsWithAuthorization, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAuthorization();
    }

    public function test_it_deactivates_and_reactivates_a_user(): void
    {
        $admin = $this->userWithRole('admin');
        $user = $this->userWithRole('waiter', [
            'username' => 'status-user',
            'password' => 'password',
            'is_active' => true,
        ]);

        Sanctum::actingAs($admin);

        $this->patchJson('/api/v1/users/'.$user->id.'/status', ['is_active' => false])
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->assertFalse($user->fresh()->is_active);

        $this->patchJson('/api/v1/users/'.$user->id.'/status', ['is_active' => true])
            ->assertOk()
            ->assertJsonPath('data.is_active', true);

        $this->assertTrue($user->fresh()->is_active);
    }

    public function test_inactive_user_cannot_login(): void
    {
        $admin = $this->userWithRole('admin');
        $user = $this->userWithRole('waiter', [
            'username' => 'inactive-login',
            'password' => 'password',
            'is_active' => true,
        ]);

        Sanctum::actingAs($admin);

        $this->patchJson('/api/v1/users/'.$user->id.'/status', ['is_active' => false])
            ->assertOk();

        $this->postJson('/api/v1/auth/login', [
            'username' => 'inactive-login',
            'password' => 'password',
        ])
            ->assertUnprocessable()
            ->assertJson([
                'message' => 'This account has been deactivated.',
            ]);
    }

    public function test_user_cannot_deactivate_themselves(): void
    {
        $admin = $this->userWithRole('admin');

        Sanctum::actingAs($admin);

        $this->patchJson('/api/v1/users/'.$admin->id.'/status', ['is_active' => false])
            ->assertConflict()
            ->assertJson([
                'success' => false,
                'message' => 'You cannot deactivate your own account.',
            ]);

        $this->assertTrue($admin->fresh()->is_active);
    }

    public function test_delete_is_rejected_to_preserve_historical_records(): void
    {
        $admin = $this->userWithRole('admin');
        $user = $this->userWithRole('waiter', ['username' => 'keep-history']);

        Sanctum::actingAs($admin);

        $this->deleteJson('/api/v1/users/'.$user->id)
            ->assertConflict()
            ->assertJson([
                'success' => false,
                'message' => 'Users cannot be permanently deleted. Deactivate the user instead.',
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'username' => 'keep-history',
        ]);
    }

    public function test_user_cannot_delete_themselves(): void
    {
        $admin = $this->userWithRole('admin');

        Sanctum::actingAs($admin);

        $this->deleteJson('/api/v1/users/'.$admin->id)
            ->assertConflict()
            ->assertJson([
                'success' => false,
                'message' => 'You cannot delete your own account.',
            ]);

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_status_request_requires_is_active(): void
    {
        $admin = $this->userWithRole('admin');
        $user = $this->userWithRole('waiter');

        Sanctum::actingAs($admin);

        $this->patchJson('/api/v1/users/'.$user->id.'/status', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['is_active']);
    }
}
