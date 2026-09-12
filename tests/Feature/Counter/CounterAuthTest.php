<?php

namespace Tests\Feature\Counter;

use App\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAuthorization;
use Tests\TestCase;

class CounterAuthTest extends TestCase
{
    use InteractsWithAuthorization, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAuthorization();
    }

    public function test_guest_is_redirected_from_dashboard_to_counter_login(): void
    {
        $this->get('/counter/dashboard')
            ->assertRedirect('/counter/login');
    }

    public function test_guest_can_view_the_counter_login_page(): void
    {
        $this->get('/counter/login')
            ->assertOk()
            ->assertSee('Sign in to account')
            ->assertSee('Counter portal');
    }

    public function test_counter_can_login_and_reach_the_dashboard(): void
    {
        $this->userWithRole('counter', [
            'username' => 'counter',
            'password' => 'password',
        ]);

        $this->post('/counter/login', [
            'username' => 'counter',
            'password' => 'password',
        ])
            ->assertRedirect('/counter/dashboard');

        $this->assertAuthenticated();

        $this->get('/counter/dashboard')
            ->assertOk()
            ->assertSee('Counter Dashboard')
            ->assertSee('Track Orders');
    }

    public function test_waiter_cannot_use_the_counter_portal(): void
    {
        $this->userWithRole('waiter', [
            'username' => 'waiter1',
            'password' => 'password',
        ]);

        $this->from('/counter/login')
            ->post('/counter/login', [
                'username' => 'waiter1',
                'password' => 'password',
            ])
            ->assertRedirect('/counter/login')
            ->assertSessionHasErrors('username');

        $this->assertGuest();

        $this->actingAs($this->userWithRole('waiter'))
            ->get('/counter/dashboard')
            ->assertForbidden();
    }

    public function test_captain_cannot_use_the_counter_portal(): void
    {
        $this->actingAs($this->userWithRole('captain'))
            ->get('/counter/dashboard')
            ->assertForbidden();
    }

    public function test_admin_cannot_use_the_counter_portal(): void
    {
        $this->userWithRole('admin', [
            'username' => 'admin1',
            'password' => 'password',
        ]);

        $this->from('/counter/login')
            ->post('/counter/login', [
                'username' => 'admin1',
                'password' => 'password',
            ])
            ->assertRedirect('/counter/login')
            ->assertSessionHasErrors('username');

        $this->actingAs($this->userWithRole('admin'))
            ->get('/counter/dashboard')
            ->assertForbidden();
    }

    public function test_counter_can_logout(): void
    {
        $counter = $this->userWithRole('counter');

        $this->actingAs($counter)
            ->post('/counter/logout')
            ->assertRedirect('/counter/login');

        $this->assertGuest();
    }

    public function test_authenticated_counter_is_redirected_away_from_login(): void
    {
        $this->actingAs($this->userWithRole('counter'))
            ->get('/counter/login')
            ->assertRedirect('/counter/dashboard');
    }

    public function test_counter_without_dashboard_view_cannot_access_dashboard(): void
    {
        $counter = $this->userWithRole('counter');
        $permission = Permission::query()->where('slug', 'dashboard.view')->firstOrFail();
        $counter->role->permissions()->detach($permission->id);

        $this->actingAs($counter->fresh())
            ->get('/counter/dashboard')
            ->assertForbidden();
    }
}
