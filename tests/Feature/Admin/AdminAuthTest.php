<?php

namespace Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAuthorization;
use Tests\TestCase;

class AdminAuthTest extends TestCase
{
    use InteractsWithAuthorization, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAuthorization();
    }

    public function test_guest_can_view_the_admin_login_page(): void
    {
        $this->get('/admin/login')
            ->assertOk()
            ->assertSee('Sign in to account');
    }

    public function test_guest_is_redirected_from_dashboard_to_login(): void
    {
        $this->get('/admin/dashboard')
            ->assertRedirect('/admin/login');
    }

    public function test_admin_can_login_and_reach_the_dashboard(): void
    {
        $this->userWithRole('admin', [
            'username' => 'admin',
            'password' => 'password',
        ]);

        $this->post('/admin/login', [
            'username' => 'admin',
            'password' => 'password',
        ])
            ->assertRedirect('/admin/dashboard');

        $this->assertAuthenticated();

        $this->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('Admin Dashboard')
            ->assertSee('Summary')
            ->assertSee('Track Orders');
    }

    public function test_invalid_password_does_not_authenticate(): void
    {
        $this->userWithRole('admin', [
            'username' => 'admin',
            'password' => 'password',
        ]);

        $this->from('/admin/login')
            ->post('/admin/login', [
                'username' => 'admin',
                'password' => 'wrong-password',
            ])
            ->assertRedirect('/admin/login')
            ->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    public function test_inactive_admin_cannot_login(): void
    {
        $this->userWithRole('admin', [
            'username' => 'admin',
            'password' => 'password',
            'is_active' => false,
        ]);

        $this->from('/admin/login')
            ->post('/admin/login', [
                'username' => 'admin',
                'password' => 'password',
            ])
            ->assertRedirect('/admin/login')
            ->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    public function test_waiter_cannot_use_the_admin_login(): void
    {
        $this->userWithRole('waiter', [
            'username' => 'waiter1',
            'password' => 'password',
        ]);

        $this->from('/admin/login')
            ->post('/admin/login', [
                'username' => 'waiter1',
                'password' => 'password',
            ])
            ->assertRedirect('/admin/login')
            ->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    public function test_counter_cannot_use_the_admin_login_yet(): void
    {
        $this->userWithRole('counter', [
            'username' => 'counter1',
            'password' => 'password',
        ]);

        $this->from('/admin/login')
            ->post('/admin/login', [
                'username' => 'counter1',
                'password' => 'password',
            ])
            ->assertRedirect('/admin/login')
            ->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    public function test_admin_can_logout(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->post('/admin/logout')
            ->assertRedirect('/admin/login');

        $this->assertGuest();
    }

    public function test_authenticated_admin_is_redirected_away_from_login(): void
    {
        $this->actingAs($this->userWithRole('admin'))
            ->get('/admin/login')
            ->assertRedirect('/admin/dashboard');
    }
}
