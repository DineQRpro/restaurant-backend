<?php

namespace Tests\Feature\Admin\Menu;

use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithAuthorization;
use Tests\TestCase;

class MenuCategoryUiTest extends TestCase
{
    use InteractsWithAuthorization, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAuthorization();
        Storage::fake('public');
    }

    public function test_guest_is_redirected_from_categories_to_login(): void
    {
        $this->get('/admin/menu/categories')
            ->assertRedirect('/admin/login');
    }

    public function test_waiter_cannot_open_admin_categories(): void
    {
        $this->actingAs($this->userWithRole('waiter'))
            ->get('/admin/menu/categories')
            ->assertForbidden();
    }

    public function test_admin_can_view_the_category_list(): void
    {
        $admin = $this->userWithRole('admin');
        MenuCategory::factory()->create(['name' => 'South Indian']);

        $this->actingAs($admin)
            ->get('/admin/menu/categories')
            ->assertOk()
            ->assertSee('Cuisine List')
            ->assertSee('South Indian')
            ->assertSee('Add New')
            ->assertSee('Categories');
    }

    public function test_empty_list_shows_the_core_php_empty_state(): void
    {
        $this->actingAs($this->userWithRole('admin'))
            ->get('/admin/menu/categories')
            ->assertOk()
            ->assertSee('There is no cuisine');
    }

    public function test_admin_can_create_a_category(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->post('/admin/menu/categories', [
                'name' => 'Main Course',
                'description' => 'Hot dishes',
                'image' => UploadedFile::fake()->image('main.jpg'),
                'sort_order' => 1,
                'is_active' => 1,
            ])
            ->assertRedirect('/admin/menu/categories')
            ->assertSessionHas('success');

        $this->assertDatabaseHas('menu_categories', [
            'name' => 'Main Course',
            'description' => 'Hot dishes',
            'is_active' => true,
        ]);

        $category = MenuCategory::query()->where('name', 'Main Course')->first();
        $this->assertNotNull($category?->image);
        Storage::disk('public')->assertExists($category->image);
    }

    public function test_create_requires_a_name(): void
    {
        $this->actingAs($this->userWithRole('admin'))
            ->from('/admin/menu/categories/create')
            ->post('/admin/menu/categories', [
                'description' => 'Missing title',
            ])
            ->assertRedirect('/admin/menu/categories/create')
            ->assertSessionHasErrors('name');
    }

    public function test_admin_can_update_a_category(): void
    {
        $admin = $this->userWithRole('admin');
        $category = MenuCategory::factory()->create(['name' => 'Old Cuisine']);

        $this->actingAs($admin)
            ->put('/admin/menu/categories/'.$category->id, [
                'name' => 'New Cuisine',
                'description' => 'Updated',
                'sort_order' => 4,
                'is_active' => 1,
            ])
            ->assertRedirect('/admin/menu/categories')
            ->assertSessionHas('success');

        $this->assertDatabaseHas('menu_categories', [
            'id' => $category->id,
            'name' => 'New Cuisine',
            'sort_order' => 4,
        ]);
    }

    public function test_admin_can_toggle_category_status(): void
    {
        $admin = $this->userWithRole('admin');
        $category = MenuCategory::factory()->create(['is_active' => true]);

        $this->actingAs($admin)
            ->patch('/admin/menu/categories/'.$category->id.'/status', [
                'is_active' => 0,
            ])
            ->assertRedirect('/admin/menu/categories');

        $this->assertFalse($category->fresh()->is_active);
    }

    public function test_admin_can_delete_a_category_without_items(): void
    {
        $admin = $this->userWithRole('admin');
        $category = MenuCategory::factory()->create();

        $this->actingAs($admin)
            ->delete('/admin/menu/categories/'.$category->id)
            ->assertRedirect('/admin/menu/categories')
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('menu_categories', ['id' => $category->id]);
    }

    public function test_admin_cannot_delete_a_category_with_items(): void
    {
        $admin = $this->userWithRole('admin');
        $category = MenuCategory::factory()->create();
        MenuItem::factory()->create(['category_id' => $category->id]);

        $this->actingAs($admin)
            ->delete('/admin/menu/categories/'.$category->id)
            ->assertRedirect('/admin/menu/categories')
            ->assertSessionHas('error');

        $this->assertDatabaseHas('menu_categories', ['id' => $category->id]);
    }

    public function test_search_filters_categories(): void
    {
        $admin = $this->userWithRole('admin');
        MenuCategory::factory()->create(['name' => 'Biriyani']);
        MenuCategory::factory()->create(['name' => 'Desserts']);

        $this->actingAs($admin)
            ->get('/admin/menu/categories?search=Biriyani')
            ->assertOk()
            ->assertSee('Biriyani')
            ->assertDontSee('Desserts');
    }

    public function test_actions_are_hidden_without_matching_permissions(): void
    {
        $admin = $this->userWithRole('admin');
        MenuCategory::factory()->create(['name' => 'Visible Cuisine']);

        $admin->role->permissions()->detach(
            Permission::query()->whereIn('slug', ['menu.create', 'menu.update', 'menu.delete'])->pluck('id'),
        );

        $this->actingAs($admin->fresh())
            ->get('/admin/menu/categories')
            ->assertOk()
            ->assertSee('Visible Cuisine')
            ->assertSee('View only')
            ->assertDontSee('Add New')
            ->assertDontSee('Edit')
            ->assertDontSee('Delete');
    }

    public function test_user_without_menu_view_cannot_list_categories(): void
    {
        $admin = $this->userWithRole('admin');
        $admin->role->permissions()->detach(
            Permission::query()->where('slug', 'menu.view')->value('id'),
        );

        $this->actingAs($admin->fresh())
            ->get('/admin/menu/categories')
            ->assertForbidden();
    }
}
