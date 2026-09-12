<?php

namespace Tests\Feature\Auth;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    private Role $role;

    protected function setUp(): void
    {
        parent::setUp();

        $this->role = Role::create([
            'name' => 'Admin',
            'slug' => 'admin',
        ]);
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'username' => 'testuser',
            'role_id' => $this->role->id,
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'username' => 'testuser',
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'user' => ['id', 'name', 'username', 'email', 'mobile', 'role', 'permissions', 'is_active'],
                    'token',
                ],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'username' => 'testuser',
                        'role' => [
                            'slug' => 'admin',
                        ],
                    ],
                ],
            ]);
    }

    public function test_login_fails_with_invalid_password(): void
    {
        User::factory()->create([
            'username' => 'testuser',
            'role_id' => $this->role->id,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'username' => 'testuser',
            'password' => 'wrongpassword',
        ]);

        $response->assertUnprocessable()
            ->assertJson([
                'message' => 'Invalid username or password.',
            ]);
    }

    public function test_login_fails_with_nonexistent_username(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'username' => 'doesnotexist',
            'password' => 'password',
        ]);

        $response->assertUnprocessable()
            ->assertJson([
                'message' => 'Invalid username or password.',
            ]);
    }

    public function test_login_fails_for_inactive_user(): void
    {
        User::factory()->create([
            'username' => 'inactiveuser',
            'role_id' => $this->role->id,
            'is_active' => false,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'username' => 'inactiveuser',
            'password' => 'password',
        ]);

        $response->assertUnprocessable()
            ->assertJson([
                'message' => 'This account has been deactivated.',
            ]);
    }

    public function test_login_requires_username_and_password(): void
    {
        $response = $this->postJson('/api/v1/auth/login', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['username', 'password']);
    }

    public function test_login_response_does_not_contain_password(): void
    {
        User::factory()->create([
            'username' => 'testuser',
            'role_id' => $this->role->id,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'username' => 'testuser',
            'password' => 'password',
        ]);

        $response->assertOk();

        $userData = $response->json('data.user');
        $this->assertArrayNotHasKey('password', $userData);
    }
}
