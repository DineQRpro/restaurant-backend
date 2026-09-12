<?php

namespace Tests\Feature\Users;

use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithAuthorization;
use Tests\TestCase;

class UserUpdateTest extends TestCase
{
    use InteractsWithAuthorization, RefreshDatabase;

    private Role $waiterRole;

    private Role $counterRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAuthorization();
        $this->waiterRole = Role::where('slug', 'waiter')->firstOrFail();
        $this->counterRole = Role::where('slug', 'counter')->firstOrFail();
        Sanctum::actingAs($this->userWithRole('admin'));
    }

    public function test_it_updates_a_user(): void
    {
        $user = $this->userWithRole('waiter', [
            'name' => 'John Doe',
            'username' => 'john',
        ]);

        $this->putJson('/api/v1/users/'.$user->id, [
            'name' => 'John Updated',
            'username' => 'john',
            'mobile' => '9876543210',
            'email' => 'john.updated@example.com',
            'role_id' => $this->counterRole->id,
            'is_active' => true,
        ])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'name' => 'John Updated',
                    'username' => 'john',
                    'mobile' => '9876543210',
                    'role' => [
                        'slug' => 'counter',
                    ],
                ],
            ])
            ->assertJsonMissingPath('data.password');
    }

    public function test_it_rejects_duplicate_username_on_update(): void
    {
        $this->userWithRole('waiter', ['username' => 'taken']);
        $user = $this->userWithRole('waiter', ['username' => 'john']);

        $this->putJson('/api/v1/users/'.$user->id, [
            'name' => 'John Doe',
            'username' => 'taken',
            'role_id' => $this->waiterRole->id,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['username']);
    }

    public function test_it_rejects_invalid_mobile_on_update(): void
    {
        $user = $this->userWithRole('waiter', ['username' => 'john']);

        $this->putJson('/api/v1/users/'.$user->id, [
            'name' => 'John Doe',
            'username' => 'john',
            'mobile' => '1987654321',
            'role_id' => $this->waiterRole->id,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['mobile']);
    }

    public function test_it_rejects_invalid_role_on_update(): void
    {
        $user = $this->userWithRole('waiter', ['username' => 'john']);

        $this->putJson('/api/v1/users/'.$user->id, [
            'name' => 'John Doe',
            'username' => 'john',
            'role_id' => 9999,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['role_id']);
    }

    public function test_password_is_unchanged_when_omitted(): void
    {
        $user = $this->userWithRole('waiter', [
            'username' => 'john',
            'password' => 'original-password',
        ]);

        $this->putJson('/api/v1/users/'.$user->id, [
            'name' => 'John Doe',
            'username' => 'john',
            'role_id' => $this->waiterRole->id,
        ])->assertOk();

        $this->assertTrue(Hash::check('original-password', $user->fresh()->password));
    }

    public function test_password_is_hashed_when_supplied(): void
    {
        $user = $this->userWithRole('waiter', [
            'username' => 'john',
            'password' => 'original-password',
        ]);

        $this->putJson('/api/v1/users/'.$user->id, [
            'name' => 'John Doe',
            'username' => 'john',
            'password' => 'new-secure-password',
            'role_id' => $this->waiterRole->id,
        ])
            ->assertOk()
            ->assertJsonMissingPath('data.password');

        $fresh = $user->fresh();
        $this->assertTrue(Hash::check('new-secure-password', $fresh->password));
        $this->assertFalse(Hash::check('original-password', $fresh->password));
        $this->assertNotSame('new-secure-password', $fresh->getRawOriginal('password'));
    }
}
