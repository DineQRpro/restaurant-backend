<?php

namespace Tests\Feature\Admin\Users;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\InteractsWithAuthorization;
use Tests\TestCase;

class UserUiTest extends TestCase
{
    use InteractsWithAuthorization, RefreshDatabase;

    private Role $waiterRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAuthorization();
        $this->waiterRole = Role::query()->where('slug', 'waiter')->firstOrFail();
    }

    public function test_guest_is_redirected_from_users_to_login(): void
    {
        $this->get('/admin/users')
            ->assertRedirect('/admin/login');
    }

    public function test_waiter_cannot_open_admin_users(): void
    {
        $this->actingAs($this->userWithRole('waiter'))
            ->get('/admin/users')
            ->assertForbidden();
    }

    public function test_admin_can_view_the_user_list(): void
    {
        $this->userWithRole('waiter', [
            'name' => 'Anita Waiter',
            'username' => 'anita',
            'mobile' => '9876543210',
        ]);

        $this->actingAs($this->userWithRole('admin'))
            ->get('/admin/users')
            ->assertOk()
            ->assertSee('Restaurant Staff')
            ->assertSee('Anita Waiter')
            ->assertSee('anita')
            ->assertSee('9876543210')
            ->assertSee('Waiter')
            ->assertSee('Add New Staff')
            ->assertSee('Users');
    }

    public function test_listing_does_not_expose_password_hashes(): void
    {
        $waiter = $this->userWithRole('waiter', [
            'name' => 'Hash Guard',
            'username' => 'hash-guard',
        ]);

        $this->actingAs($this->userWithRole('admin'))
            ->get('/admin/users')
            ->assertOk()
            ->assertSee('Hash Guard')
            ->assertDontSee($waiter->getRawOriginal('password'), false);
    }

    public function test_search_and_filters_are_applied_together(): void
    {
        $this->userWithRole('waiter', [
            'name' => 'Active Waiter',
            'username' => 'active-waiter',
            'is_active' => true,
        ]);
        $this->userWithRole('waiter', [
            'name' => 'Inactive Waiter',
            'username' => 'inactive-waiter',
            'is_active' => false,
        ]);
        $this->userWithRole('captain', [
            'name' => 'Active Captain',
            'username' => 'active-captain',
        ]);

        $this->actingAs($this->userWithRole('admin'))
            ->get('/admin/users?search=Waiter&role=waiter&is_active=1')
            ->assertOk()
            ->assertSee('Active Waiter')
            ->assertDontSee('Inactive Waiter')
            ->assertDontSee('Active Captain');
    }

    public function test_filtered_empty_state_is_specific(): void
    {
        $this->actingAs($this->userWithRole('admin'))
            ->get('/admin/users?search=DoesNotExist')
            ->assertOk()
            ->assertSee('No staff match your search or filters.');
    }

    public function test_admin_can_open_the_create_form(): void
    {
        $this->actingAs($this->userWithRole('admin'))
            ->get('/admin/users/create')
            ->assertOk()
            ->assertSee('Add new restaurant staff')
            ->assertSee('Waiter')
            ->assertSee('Captain')
            ->assertSee('Counter')
            ->assertSee('Admin');
    }

    public function test_admin_can_create_a_user(): void
    {
        $this->actingAs($this->userWithRole('admin'))
            ->post('/admin/users', [
                'name' => 'John Doe',
                'username' => 'john',
                'mobile' => '9876543210',
                'email' => 'john@example.com',
                'password' => 'secure-password',
                'password_confirmation' => 'secure-password',
                'role_id' => $this->waiterRole->id,
                'is_active' => 1,
            ])
            ->assertRedirect('/admin/users')
            ->assertSessionHas('success');

        $user = User::query()->where('username', 'john')->first();

        $this->assertNotNull($user);
        $this->assertSame('John Doe', $user->name);
        $this->assertSame('9876543210', $user->mobile);
        $this->assertTrue($user->is_active);
        $this->assertTrue(Hash::check('secure-password', $user->password));
    }

    public function test_create_validates_required_fields(): void
    {
        $this->actingAs($this->userWithRole('admin'))
            ->from('/admin/users/create')
            ->post('/admin/users', [])
            ->assertRedirect('/admin/users/create')
            ->assertSessionHasErrors(['name', 'username', 'password', 'role_id']);
    }

    public function test_create_rejects_invalid_mobile_and_duplicate_username(): void
    {
        $this->userWithRole('waiter', ['username' => 'taken']);

        $this->actingAs($this->userWithRole('admin'))
            ->from('/admin/users/create')
            ->post('/admin/users', [
                'name' => 'John Doe',
                'username' => 'taken',
                'mobile' => '1987654321',
                'password' => 'secure-password',
                'password_confirmation' => 'secure-password',
                'role_id' => $this->waiterRole->id,
            ])
            ->assertRedirect('/admin/users/create')
            ->assertSessionHasErrors(['username', 'mobile']);
    }

    public function test_admin_can_update_a_user_without_changing_password(): void
    {
        $user = $this->userWithRole('waiter', [
            'name' => 'Old Name',
            'username' => 'old-user',
            'password' => 'original-password',
        ]);

        $this->actingAs($this->userWithRole('admin'))
            ->put('/admin/users/'.$user->id, [
                'name' => 'New Name',
                'username' => 'new-user',
                'mobile' => '9123456789',
                'email' => 'new@example.com',
                'role_id' => $this->waiterRole->id,
                'is_active' => 1,
            ])
            ->assertRedirect('/admin/users')
            ->assertSessionHas('success');

        $user->refresh();

        $this->assertSame('New Name', $user->name);
        $this->assertSame('new-user', $user->username);
        $this->assertTrue(Hash::check('original-password', $user->password));
    }

    public function test_admin_can_change_a_user_password_from_edit(): void
    {
        $user = $this->userWithRole('waiter', [
            'username' => 'reset-me',
            'password' => 'original-password',
        ]);

        $this->actingAs($this->userWithRole('admin'))
            ->put('/admin/users/'.$user->id, [
                'name' => $user->name,
                'username' => 'reset-me',
                'role_id' => $this->waiterRole->id,
                'password' => 'changed-password',
                'password_confirmation' => 'changed-password',
                'is_active' => 1,
            ])
            ->assertRedirect('/admin/users');

        $this->assertTrue(Hash::check('changed-password', $user->fresh()->password));
    }

    public function test_admin_can_toggle_status(): void
    {
        $user = $this->userWithRole('waiter', [
            'username' => 'status-user',
            'is_active' => true,
        ]);

        $this->actingAs($this->userWithRole('admin'))
            ->patch('/admin/users/'.$user->id.'/status', [
                'is_active' => 0,
            ])
            ->assertRedirect('/admin/users')
            ->assertSessionHas('success');

        $this->assertFalse($user->fresh()->is_active);
    }

    public function test_admin_cannot_deactivate_themselves(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->from('/admin/users')
            ->patch('/admin/users/'.$admin->id.'/status', [
                'is_active' => 0,
            ])
            ->assertRedirect('/admin/users')
            ->assertSessionHas('error', 'You cannot deactivate your own account.');

        $this->assertTrue($admin->fresh()->is_active);
    }

    public function test_delete_is_rejected_and_user_remains(): void
    {
        $user = $this->userWithRole('waiter', ['username' => 'keep-history']);

        $this->actingAs($this->userWithRole('admin'))
            ->from('/admin/users')
            ->delete('/admin/users/'.$user->id)
            ->assertRedirect('/admin/users')
            ->assertSessionHas('error', 'Users cannot be permanently deleted. Deactivate the user instead.');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'username' => 'keep-history',
        ]);
    }

    public function test_admin_cannot_delete_themselves(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->from('/admin/users')
            ->delete('/admin/users/'.$admin->id)
            ->assertRedirect('/admin/users')
            ->assertSessionHas('error', 'You cannot delete your own account.');

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_list_hides_self_deactivate_and_delete(): void
    {
        $admin = $this->userWithRole('admin', ['name' => 'Solo Admin']);

        $this->actingAs($admin)
            ->get('/admin/users')
            ->assertOk()
            ->assertSee('Solo Admin')
            ->assertSee('Edit')
            ->assertDontSee('Deactivate')
            ->assertDontSee('Delete');
    }

    public function test_listing_paginates_and_preserves_query_string(): void
    {
        foreach (range(1, 21) as $i) {
            $this->userWithRole('waiter', [
                'name' => sprintf('Waiter %02d', $i),
                'username' => 'waiter-'.$i,
            ]);
        }

        $this->actingAs($this->userWithRole('admin'))
            ->get('/admin/users?role=waiter')
            ->assertOk()
            ->assertSee('page=2');
    }

    public function test_actions_are_hidden_without_matching_permissions(): void
    {
        $admin = $this->userWithRole('admin');
        $this->userWithRole('waiter', [
            'name' => 'Visible Staff',
            'username' => 'visible-staff',
        ]);

        $admin->role->permissions()->detach(
            Permission::query()->whereIn('slug', ['users.create', 'users.update', 'users.delete'])->pluck('id'),
        );

        $this->actingAs($admin->fresh())
            ->get('/admin/users')
            ->assertOk()
            ->assertSee('Visible Staff')
            ->assertSee('View only')
            ->assertDontSee('Add New Staff')
            ->assertDontSee('Edit')
            ->assertDontSee('Delete');
    }

    public function test_user_without_users_view_cannot_list_users(): void
    {
        $admin = $this->userWithRole('admin');
        $admin->role->permissions()->detach(
            Permission::query()->where('slug', 'users.view')->value('id'),
        );

        $this->actingAs($admin->fresh())
            ->get('/admin/users')
            ->assertForbidden();
    }

    public function test_create_requires_users_create(): void
    {
        $admin = $this->userWithRole('admin');
        $admin->role->permissions()->detach(
            Permission::query()->where('slug', 'users.create')->value('id'),
        );

        $this->actingAs($admin->fresh())
            ->get('/admin/users/create')
            ->assertForbidden();
    }
}
