<?php

namespace Database\Seeders;

use App\Models\Table;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TableSeeder extends Seeder
{
    public function run(): void
    {
        $capacities = [2, 4, 2, 4, 4, 2, 4, 4, 2, 4];

        foreach ($capacities as $index => $capacity) {
            $number = (string) ($index + 1);

            Table::query()->firstOrCreate(
                ['number' => $number],
                [
                    'description' => $capacity === 2 ? 'Two-seater' : 'Four-seater',
                    'capacity' => $capacity,
                    'code' => (string) Str::ulid(),
                    'sort_order' => $index + 1,
                    'is_active' => true,
                ],
            );
        }
    }
}
