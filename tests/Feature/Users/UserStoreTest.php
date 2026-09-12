<?php

namespace Tests\Feature\Users;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithAuthorization;
use Tests\TestCase;

class UserStoreTest extends TestCase
{
    use InteractsWithAuthorization, RefreshDatabase;

    private Role $waiterRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAuthorization();
        $this->waiterRole = Role::where('slug', 'waiter')->firstOrFail();
        Sanctum::actingAs($this->userWithRole('admin'));
    }

    public function test_it_creates_a_valid_user(): void
    {
        $response = $this->postJson('/api/v1/users', [
            'name' => 'John Doe',
            'username' => 'john',
            'mobile' => '9876543210',
            'email' => 'john@example.com',
            'password' => 'secure-password',
            'role_id' => $this->waiterRole->id,
        ]);

        $response->assertCreated()
            ->assertJson([
                'success' => true,
                'message' => 'User created successfully.',
                'data' => [
                    'name' => 'John Doe',
                    'username' => 'john',
                    'mobile' => '9876543210',
                    'email' => 'john@example.com',
                    'is_active' => true,
                    'role' => [
                        'slug' => 'waiter',
                    ],
                ],
            ]);

        $this->assertArrayNotHasKey('password', $response->json('data'));
        $this->assertArrayNotHasKey('permissions', $response->json('data'));

        $user = User::where('username', 'john')->first();
        $this->assertNotNull($user);
        $this->assertTrue(Hash::check('secure-password', $user->password));
        $this->assertNotSame('secure-password', $user->getRawOriginal('password'));
    }

    public function test_it_rejects_duplicate_username(): void
    {
        $this->userWithRole('waiter', ['username' => 'john']);

        $this->postJson('/api/v1/users', $this->validPayload([
            'username' => 'john',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['username']);
    }

    public function test_it_rejects_invalid_mobile_numbers(): void
    {
        foreach (['987654321', '1987654321', '98765432101', 'abcdefghij'] as $mobile) {
            $this->postJson('/api/v1/users', $this->validPayload([
                'username' => 'user-'.$mobile,
                'mobile' => $mobile,
            ]))
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['mobile']);
        }
    }

    public function test_it_rejects_invalid_email(): void
    {
        $this->postJson('/api/v1/users', $this->validPayload([
            'email' => 'not-an-email',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_it_rejects_invalid_role(): void
    {
        $this->postJson('/api/v1/users', $this->validPayload([
            'role_id' => 9999,
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['role_id']);
    }

    public function test_it_rejects_missing_required_fields(): void
    {
        $this->postJson('/api/v1/users', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'username', 'password', 'role_id']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'John Doe',
            'username' => 'john',
            'password' => 'secure-password',
            'role_id' => $this->waiterRole->id,
        ], $overrides);
    }
}
