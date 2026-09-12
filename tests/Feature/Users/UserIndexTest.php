<?php

namespace Tests\Feature\Users;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithAuthorization;
use Tests\TestCase;

class UserIndexTest extends TestCase
{
    use InteractsWithAuthorization, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAuthorization();
        Sanctum::actingAs($this->userWithRole('admin', [
            'name' => 'System Admin',
            'username' => 'admin-lister',
            'email' => 'admin.lister@example.com',
        ]));
    }

    public function test_it_lists_users_with_role_and_without_password(): void
    {
        $this->userWithRole('waiter', [
            'name' => 'John Waiter',
            'username' => 'johnwaiter',
        ]);

        $response = $this->getJson('/api/v1/users');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Users retrieved successfully.',
            ])
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'username',
                        'mobile',
                        'email',
                        'role' => ['id', 'name', 'slug'],
                        'is_active',
                        'created_at',
                        'updated_at',
                    ],
                ],
                'meta' => ['current_page', 'per_page', 'total', 'last_page'],
            ]);

        $firstUser = $response->json('data.0');
        $this->assertArrayNotHasKey('password', $firstUser);
        $this->assertArrayNotHasKey('permissions', $firstUser);
    }

    public function test_it_paginates_users(): void
    {
        $waiterRole = Role::where('slug', 'waiter')->first();

        User::factory()->count(25)->create(['role_id' => $waiterRole->id]);

        $response = $this->getJson('/api/v1/users?per_page=10');

        $response->assertOk()
            ->assertJsonPath('meta.per_page', 10)
            ->assertJsonPath('meta.current_page', 1);

        $this->assertCount(10, $response->json('data'));
        $this->assertGreaterThan(10, $response->json('meta.total'));
    }

    public function test_it_searches_users_by_name_username_mobile_and_email(): void
    {
        $this->userWithRole('waiter', [
            'name' => 'John Doe',
            'username' => 'johndoe',
            'email' => 'john.search@example.com',
            'mobile' => '9876543210',
        ]);
        $this->userWithRole('waiter', [
            'name' => 'Jane Smith',
            'username' => 'janesmith',
            'email' => 'jane.search@example.com',
        ]);

        $this->getJson('/api/v1/users?search=john')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.username', 'johndoe');

        $this->getJson('/api/v1/users?search=9876543210')
            ->assertOk()
            ->assertJsonPath('data.0.username', 'johndoe');
    }

    public function test_it_filters_users_by_role_slug(): void
    {
        $this->userWithRole('waiter', ['username' => 'filter-waiter']);
        $this->userWithRole('counter', ['username' => 'filter-counter']);

        $response = $this->getJson('/api/v1/users?role=waiter');

        $response->assertOk();

        $slugs = collect($response->json('data'))->pluck('role.slug')->unique()->values()->all();

        $this->assertEquals(['waiter'], $slugs);
    }

    public function test_it_filters_users_by_active_status(): void
    {
        $this->userWithRole('waiter', [
            'username' => 'active-user',
            'is_active' => true,
        ]);
        $this->userWithRole('waiter', [
            'username' => 'inactive-user',
            'is_active' => false,
        ]);

        $this->getJson('/api/v1/users?is_active=1')
            ->assertOk();

        $activeUsernames = collect($this->getJson('/api/v1/users?is_active=1')->json('data'))
            ->pluck('username');
        $inactiveUsernames = collect($this->getJson('/api/v1/users?is_active=0')->json('data'))
            ->pluck('username');

        $this->assertTrue($activeUsernames->contains('active-user'));
        $this->assertFalse($activeUsernames->contains('inactive-user'));
        $this->assertTrue($inactiveUsernames->contains('inactive-user'));
    }

    public function test_it_rejects_invalid_role_filter(): void
    {
        $this->getJson('/api/v1/users?role=unknown')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['role']);
    }
}
