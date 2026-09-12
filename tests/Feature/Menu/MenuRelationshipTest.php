<?php

namespace Tests\Feature\Menu;

use App\Models\MenuCategory;
use App\Models\MenuItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MenuRelationshipTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_has_many_items_and_item_belongs_to_category(): void
    {
        $category = MenuCategory::factory()->create(['name' => 'Starters']);
        $item = MenuItem::factory()->create([
            'name' => 'Gobi Manchurian',
            'category_id' => $category->id,
        ]);

        $this->assertTrue($category->items->contains($item));
        $this->assertEquals(1, $category->items()->count());
        $this->assertTrue($item->category->is($category));
        $this->assertEquals('Starters', $item->category->name);
    }
}
