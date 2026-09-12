<?php

namespace Tests\Feature\Auth;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MeTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_get_their_profile(): void
    {
        $role = Role::create(['name' => 'Waiter', 'slug' => 'waiter']);
        $user = User::factory()->create([
            'username' => 'john',
            'role_id' => $role->id,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/auth/me');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $user->id,
                    'username' => 'john',
                    'role' => [
                        'slug' => 'waiter',
                    ],
                ],
            ])
            ->assertJsonStructure([
                'data' => ['permissions'],
            ]);

        $userData = $response->json('data');
        $this->assertArrayNotHasKey('password', $userData);
        $this->assertIsArray($response->json('data.permissions'));
    }

    public function test_unauthenticated_user_cannot_access_me(): void
    {
        $response = $this->getJson('/api/v1/auth/me');

        $response->assertUnauthorized();
    }
}
