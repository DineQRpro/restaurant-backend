<?php

namespace Tests\Feature\Admin\Menu;

use App\Enums\DietaryType;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAuthorization;
use Tests\TestCase;

class MenuItemUiTest extends TestCase
{
    use InteractsWithAuthorization, RefreshDatabase;

    private MenuCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAuthorization();
        $this->category = MenuCategory::factory()->create(['name' => 'Main Course']);
    }

    public function test_guest_is_redirected_from_items_to_login(): void
    {
        $this->get('/admin/menu/items')
            ->assertRedirect('/admin/login');
    }

    public function test_waiter_cannot_open_admin_items(): void
    {
        $this->actingAs($this->userWithRole('waiter'))
            ->get('/admin/menu/items')
            ->assertForbidden();
    }

    public function test_admin_can_view_the_item_list(): void
    {
        MenuItem::factory()->create([
            'name' => 'Chicken Biriyani',
            'category_id' => $this->category->id,
            'price' => '180.00',
        ]);

        $this->actingAs($this->userWithRole('admin'))
            ->get('/admin/menu/items')
            ->assertOk()
            ->assertSee('Restaurant Menu List')
            ->assertSee('Chicken Biriyani')
            ->assertSee('Main Course')
            ->assertSee('Add Menu Item')
            ->assertSee('Items');
    }

    public function test_empty_list_shows_an_empty_state(): void
    {
        $this->actingAs($this->userWithRole('admin'))
            ->get('/admin/menu/items')
            ->assertOk()
            ->assertSee('No menu items found.');
    }

    public function test_search_and_filters_are_applied_together(): void
    {
        $sides = MenuCategory::factory()->create(['name' => 'Sides']);

        MenuItem::factory()->create([
            'name' => 'Veg Burger',
            'category_id' => $this->category->id,
            'dietary_type' => DietaryType::Vegetarian,
            'is_available' => true,
        ]);
        MenuItem::factory()->unavailable()->create([
            'name' => 'Veg Burger Closed',
            'category_id' => $this->category->id,
            'dietary_type' => DietaryType::Vegetarian,
        ]);
        MenuItem::factory()->create([
            'name' => 'Chicken Burger',
            'category_id' => $sides->id,
            'dietary_type' => DietaryType::NonVegetarian,
        ]);

        $this->actingAs($this->userWithRole('admin'))
            ->get('/admin/menu/items?search=Burger&category_id='.$this->category->id.'&dietary_type=vegetarian&is_available=1')
            ->assertOk()
            ->assertSee('Veg Burger')
            ->assertDontSee('Veg Burger Closed')
            ->assertDontSee('Chicken Burger');
    }

    public function test_filtered_empty_state_is_specific(): void
    {
        MenuItem::factory()->create([
            'name' => 'Idli',
            'category_id' => $this->category->id,
        ]);

        $this->actingAs($this->userWithRole('admin'))
            ->get('/admin/menu/items?search=DoesNotExist')
            ->assertOk()
            ->assertSee('No menu items match your search or filters.');
    }

    public function test_admin_can_create_a_menu_item(): void
    {
        $this->actingAs($this->userWithRole('admin'))
            ->post('/admin/menu/items', [
                'name' => 'Chicken Biriyani',
                'description' => 'Hot',
                'category_id' => $this->category->id,
                'dietary_type' => DietaryType::NonVegetarian->value,
                'price' => '180.50',
                'spicy_level' => 2,
                'sort_order' => 1,
                'is_available' => 1,
                'is_chef_special' => 1,
            ])
            ->assertRedirect('/admin/menu/items')
            ->assertSessionHas('success');

        $this->assertDatabaseHas('menu_items', [
            'name' => 'Chicken Biriyani',
            'category_id' => $this->category->id,
            'price' => '180.50',
            'is_chef_special' => true,
            'spicy_level' => 2,
        ]);
    }

    public function test_create_validates_required_fields(): void
    {
        $this->actingAs($this->userWithRole('admin'))
            ->from('/admin/menu/items/create')
            ->post('/admin/menu/items', [])
            ->assertRedirect('/admin/menu/items/create')
            ->assertSessionHasErrors(['name', 'category_id', 'dietary_type', 'price']);
    }

    public function test_create_rejects_an_inactive_category(): void
    {
        $inactive = MenuCategory::factory()->inactive()->create(['name' => 'Archived']);

        $this->actingAs($this->userWithRole('admin'))
            ->from('/admin/menu/items/create')
            ->post('/admin/menu/items', [
                'name' => 'Hidden Dish',
                'category_id' => $inactive->id,
                'dietary_type' => DietaryType::Vegetarian->value,
                'price' => '10.00',
            ])
            ->assertRedirect('/admin/menu/items/create')
            ->assertSessionHasErrors('category_id');
    }

    public function test_create_rejects_a_missing_category(): void
    {
        $this->actingAs($this->userWithRole('admin'))
            ->from('/admin/menu/items/create')
            ->post('/admin/menu/items', [
                'name' => 'Ghost Dish',
                'category_id' => 9999,
                'dietary_type' => DietaryType::Vegetarian->value,
                'price' => '10.00',
            ])
            ->assertRedirect('/admin/menu/items/create')
            ->assertSessionHasErrors('category_id');
    }

    public function test_admin_can_update_a_menu_item(): void
    {
        $item = MenuItem::factory()->create([
            'name' => 'Old Dish',
            'category_id' => $this->category->id,
            'price' => '50.00',
        ]);

        $this->actingAs($this->userWithRole('admin'))
            ->put('/admin/menu/items/'.$item->id, [
                'name' => 'New Dish',
                'description' => 'Updated',
                'category_id' => $this->category->id,
                'dietary_type' => DietaryType::Vegetarian->value,
                'price' => '75.25',
                'spicy_level' => 1,
                'sort_order' => 3,
                'is_available' => 1,
                'is_chef_special' => 0,
            ])
            ->assertRedirect('/admin/menu/items')
            ->assertSessionHas('success');

        $this->assertDatabaseHas('menu_items', [
            'id' => $item->id,
            'name' => 'New Dish',
            'price' => '75.25',
            'spicy_level' => 1,
        ]);
    }

    public function test_edit_can_keep_an_existing_inactive_category(): void
    {
        $inactive = MenuCategory::factory()->inactive()->create(['name' => 'Old Cuisine']);
        $item = MenuItem::factory()->create([
            'name' => 'Legacy Dish',
            'category_id' => $inactive->id,
        ]);

        $this->actingAs($this->userWithRole('admin'))
            ->put('/admin/menu/items/'.$item->id, [
                'name' => 'Legacy Dish',
                'category_id' => $inactive->id,
                'dietary_type' => DietaryType::Vegetarian->value,
                'price' => '20.00',
                'is_available' => 1,
                'is_chef_special' => 0,
            ])
            ->assertRedirect('/admin/menu/items');

        $this->assertSame($inactive->id, $item->fresh()->category_id);
    }

    public function test_admin_can_toggle_availability(): void
    {
        $item = MenuItem::factory()->create([
            'category_id' => $this->category->id,
            'is_available' => true,
        ]);

        $this->actingAs($this->userWithRole('admin'))
            ->patch('/admin/menu/items/'.$item->id.'/status', [
                'is_available' => 0,
            ])
            ->assertRedirect('/admin/menu/items');

        $this->assertFalse($item->fresh()->is_available);
    }

    public function test_admin_can_delete_a_menu_item(): void
    {
        $item = MenuItem::factory()->create(['category_id' => $this->category->id]);

        $this->actingAs($this->userWithRole('admin'))
            ->delete('/admin/menu/items/'.$item->id)
            ->assertRedirect('/admin/menu/items')
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('menu_items', ['id' => $item->id]);
    }

    public function test_listing_paginates_and_preserves_query_string(): void
    {
        MenuItem::factory()->count(21)->create(['category_id' => $this->category->id]);

        $this->actingAs($this->userWithRole('admin'))
            ->get('/admin/menu/items?category_id='.$this->category->id)
            ->assertOk()
            ->assertSee('page=2');
    }

    public function test_actions_are_hidden_without_matching_permissions(): void
    {
        $admin = $this->userWithRole('admin');
        MenuItem::factory()->create([
            'name' => 'Visible Dish',
            'category_id' => $this->category->id,
        ]);

        $admin->role->permissions()->detach(
            Permission::query()->whereIn('slug', ['menu.create', 'menu.update', 'menu.delete'])->pluck('id'),
        );

        $this->actingAs($admin->fresh())
            ->get('/admin/menu/items')
            ->assertOk()
            ->assertSee('Visible Dish')
            ->assertSee('View only')
            ->assertDontSee('Add Menu Item')
            ->assertDontSee('Edit')
            ->assertDontSee('Delete');
    }

    public function test_user_without_menu_view_cannot_list_items(): void
    {
        $admin = $this->userWithRole('admin');
        $admin->role->permissions()->detach(
            Permission::query()->where('slug', 'menu.view')->value('id'),
        );

        $this->actingAs($admin->fresh())
            ->get('/admin/menu/items')
            ->assertForbidden();
    }

    public function test_create_requires_menu_create(): void
    {
        $admin = $this->userWithRole('admin');
        $admin->role->permissions()->detach(
            Permission::query()->where('slug', 'menu.create')->value('id'),
        );

        $this->actingAs($admin->fresh())
            ->get('/admin/menu/items/create')
            ->assertForbidden();
    }
}
