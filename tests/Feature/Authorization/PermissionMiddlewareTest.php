<?php

namespace Tests\Feature\Authorization;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PermissionMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RoleSeeder::class,
            PermissionSeeder::class,
            RolePermissionSeeder::class,
        ]);

        // Register a test-only route protected by the permission middleware
        Route::middleware(['auth:sanctum', 'permission:orders.create'])
            ->get('/api/v1/test/permission-check', function () {
                return response()->json([
                    'success' => true,
                    'message' => 'Access granted.',
                    'data' => null,
                ]);
            });
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $response = $this->getJson('/api/v1/test/permission-check');

        $response->assertUnauthorized();
    }

    public function test_authenticated_user_with_permission_can_access(): void
    {
        $waiterRole = Role::where('slug', 'waiter')->first();
        $user = User::factory()->create(['role_id' => $waiterRole->id]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/test/permission-check');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Access granted.',
            ]);
    }

    public function test_authenticated_user_without_permission_gets_403(): void
    {
        $counterRole = Role::where('slug', 'counter')->first();
        $user = User::factory()->create(['role_id' => $counterRole->id]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/test/permission-check');

        $response->assertForbidden()
            ->assertJson([
                'success' => false,
                'message' => 'You do not have permission to perform this action.',
            ]);
    }
}
