<?php

namespace Tests\Feature\Users;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithAuthorization;
use Tests\TestCase;

class UserShowTest extends TestCase
{
    use InteractsWithAuthorization, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAuthorization();
        Sanctum::actingAs($this->userWithRole('admin'));
    }

    public function test_it_shows_an_existing_user(): void
    {
        $user = $this->userWithRole('waiter', [
            'name' => 'John Doe',
            'username' => 'john',
        ]);

        $this->getJson('/api/v1/users/'.$user->id)
            ->assertOk()
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
            ->assertJsonMissingPath('data.password')
            ->assertJsonMissingPath('data.permissions');
    }

    public function test_it_returns_404_for_a_nonexistent_user(): void
    {
        $this->getJson('/api/v1/users/9999')
            ->assertNotFound()
            ->assertJson([
                'success' => false,
                'message' => 'The requested resource was not found.',
            ]);
    }
}
