<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class FloorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Floor::updateOrCreate(
        ['code' => 'MAIN'],
        [
            'name' => 'Main Floor',
            'description' => 'Main restaurant floor',
            'sort_order' => 1,
            'is_active' => true,
        ]
    );
    }
}
