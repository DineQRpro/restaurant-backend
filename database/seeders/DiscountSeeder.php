<?php

namespace Database\Seeders;

use App\Models\Discount;
use Illuminate\Database\Seeder;

class DiscountSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Discount::updateOrCreate(
            ['name' => 'Others'],
            [
                'type' => 'custom',
                'value' => 0.00,
                'min_level' => 1,
                'max_level' => 30,
                'applies_to' => 'order',
                'start_date' => null,
                'end_date' => null,
                'is_active' => true,
            ]
        );

        Discount::updateOrCreate(
            ['name' => 'Non-Chargeable'],
            [
                'type' => 'fixed',
                'value' => 100.00,
                'min_level' => 0,
                'max_level' => 0,
                'applies_to' => 'order',
                'start_date' => null,
                'end_date' => null,
                'is_active' => true,
            ]
        );

        Discount::updateOrCreate(
            ['name' => 'Discount 5%'],
            [
                'type' => 'fixed',
                'value' => 5.00,
                'min_level' => 0,
                'max_level' => 0,
                'applies_to' => 'order',
                'start_date' => null,
                'end_date' => null,
                'is_active' => true,
            ]
        );

        Discount::updateOrCreate(
            ['name' => 'Discount 10%'],
            [
                'type' => 'fixed',
                'value' => 10.00,
                'min_level' => 0,
                'max_level' => 0,
                'applies_to' => 'order',
                'start_date' => null,
                'end_date' => null,
                'is_active' => true,
            ]
        );

        Discount::updateOrCreate(
            ['name' => 'Discount 15%'],
            [
                'type' => 'fixed',
                'value' => 15.00,
                'min_level' => 0,
                'max_level' => 0,
                'applies_to' => 'order',
                'start_date' => null,
                'end_date' => null,
                'is_active' => true,
            ]
        );
    }
}