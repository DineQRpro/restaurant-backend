<?php

namespace Database\Seeders;

use App\Models\DineType;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DineTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DineType::updateOrCreate(
            ['slug' => 'dine-in'],
            [
                'name' => 'Dine In',
                'description' => 'Restaurant dine-in service',
                'sort_order' => 1,
                'is_active' => true,
            ]
        );

        DineType::updateOrCreate(
            ['slug' => 'take-away'],
            [
                'name' => 'Take Away',
                'description' => 'Take away order',
                'sort_order' => 2,
                'is_active' => true,
            ]
        );

        DineType::updateOrCreate(
            ['slug' => 'couple-dine'],
            [
                'name' => 'Couple Dine',
                'description' => 'Couple dining service',
                'sort_order' => 3,
                'is_active' => true,
            ]
        );

        DineType::updateOrCreate(
            ['slug' => 'candle-light-dinner'],
            [
                'name' => 'Candle Light Dinner',
                'description' => 'Candle light dinner service',
                'sort_order' => 4,
                'is_active' => true,
            ]
        );
    }
}
