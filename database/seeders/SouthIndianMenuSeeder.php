<?php

namespace Database\Seeders;

use App\Enums\DietaryType;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use Illuminate\Database\Seeder;

class SouthIndianMenuSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Tiffin',
                'description' => 'South Indian breakfast plates',
                'sort_order' => 1,
                'items' => [
                    ['name' => 'Idli (2 pcs)', 'description' => 'Steamed rice cakes with sambar and chutney', 'price' => '40.00', 'dietary_type' => DietaryType::Vegetarian, 'spicy_level' => 0],
                    ['name' => 'Medu Vada (2 pcs)', 'description' => 'Crispy lentil doughnuts', 'price' => '50.00', 'dietary_type' => DietaryType::Vegetarian, 'spicy_level' => 1],
                    ['name' => 'Ven Pongal', 'description' => 'Pepper-jeera rice and moong dal', 'price' => '70.00', 'dietary_type' => DietaryType::Vegetarian, 'spicy_level' => 1, 'is_chef_special' => true],
                    ['name' => 'Poori Masala', 'description' => 'Puffed pooris with potato masala', 'price' => '80.00', 'dietary_type' => DietaryType::Vegetarian, 'spicy_level' => 1],
                ],
            ],
            [
                'name' => 'Dosa',
                'description' => 'Crispy rice and lentil crepes',
                'sort_order' => 2,
                'items' => [
                    ['name' => 'Plain Dosa', 'description' => 'Crispy dosa with chutney and sambar', 'price' => '60.00', 'dietary_type' => DietaryType::Vegetarian, 'spicy_level' => 0],
                    ['name' => 'Masala Dosa', 'description' => 'Dosa stuffed with spiced potato', 'price' => '80.00', 'dietary_type' => DietaryType::Vegetarian, 'spicy_level' => 1],
                    ['name' => 'Onion Dosa', 'description' => 'Dosa topped with chopped onion', 'price' => '75.00', 'dietary_type' => DietaryType::Vegetarian, 'spicy_level' => 1],
                    ['name' => 'Ghee Roast', 'description' => 'Thin crispy dosa roasted in ghee', 'price' => '95.00', 'dietary_type' => DietaryType::Vegetarian, 'spicy_level' => 1, 'is_chef_special' => true],
                    ['name' => 'Egg Dosa', 'description' => 'Dosa topped with seasoned egg', 'price' => '90.00', 'dietary_type' => DietaryType::Eggetarian, 'spicy_level' => 1],
                ],
            ],
            [
                'name' => 'Rice',
                'description' => 'South Indian rice meals',
                'sort_order' => 3,
                'items' => [
                    ['name' => 'Sambar Rice', 'description' => 'Rice mixed with sambar and vegetables', 'price' => '90.00', 'dietary_type' => DietaryType::Vegetarian, 'spicy_level' => 1],
                    ['name' => 'Curd Rice', 'description' => 'Tempered yogurt rice', 'price' => '70.00', 'dietary_type' => DietaryType::Vegetarian, 'spicy_level' => 0],
                    ['name' => 'Lemon Rice', 'description' => 'Tangy tempered rice with peanuts', 'price' => '75.00', 'dietary_type' => DietaryType::Vegetarian, 'spicy_level' => 1],
                    ['name' => 'Chicken Biryani', 'description' => 'Seeraga samba chicken biryani', 'price' => '180.00', 'dietary_type' => DietaryType::NonVegetarian, 'spicy_level' => 2, 'is_chef_special' => true],
                ],
            ],
            [
                'name' => 'Gravies',
                'description' => 'Curries and side gravies',
                'sort_order' => 4,
                'items' => [
                    ['name' => 'Sambar', 'description' => 'Lentil and tamarind gravy', 'price' => '40.00', 'dietary_type' => DietaryType::Vegetarian, 'spicy_level' => 1],
                    ['name' => 'Rasam', 'description' => 'Pepper-tamarind soup', 'price' => '35.00', 'dietary_type' => DietaryType::Vegetarian, 'spicy_level' => 2],
                    ['name' => 'Vegetable Kurma', 'description' => 'Coconut milk vegetable gravy', 'price' => '90.00', 'dietary_type' => DietaryType::Vegetarian, 'spicy_level' => 1],
                    ['name' => 'Chicken Chettinad', 'description' => 'Pepper-chettinad chicken gravy', 'price' => '160.00', 'dietary_type' => DietaryType::NonVegetarian, 'spicy_level' => 2],
                ],
            ],
            [
                'name' => 'Beverages',
                'description' => 'Hot and cold drinks',
                'sort_order' => 5,
                'items' => [
                    ['name' => 'Filter Coffee', 'description' => 'South Indian decoction coffee', 'price' => '30.00', 'dietary_type' => DietaryType::Vegetarian, 'spicy_level' => 0],
                    ['name' => 'Buttermilk', 'description' => 'Salted spiced buttermilk', 'price' => '25.00', 'dietary_type' => DietaryType::Vegetarian, 'spicy_level' => 0],
                ],
            ],
        ];

        foreach ($categories as $categoryData) {
            $items = $categoryData['items'];
            unset($categoryData['items']);

            $category = MenuCategory::query()->firstOrCreate(
                ['name' => $categoryData['name']],
                [
                    'description' => $categoryData['description'],
                    'sort_order' => $categoryData['sort_order'],
                    'is_active' => true,
                ],
            );

            foreach ($items as $index => $item) {
                MenuItem::query()->firstOrCreate(
                    [
                        'name' => $item['name'],
                        'category_id' => $category->id,
                    ],
                    [
                        'description' => $item['description'],
                        'price' => $item['price'],
                        'dietary_type' => $item['dietary_type'],
                        'spicy_level' => $item['spicy_level'],
                        'is_available' => true,
                        'is_chef_special' => $item['is_chef_special'] ?? false,
                        'sort_order' => $index + 1,
                    ],
                );
            }
        }
    }
}
