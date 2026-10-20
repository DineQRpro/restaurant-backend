<?php

namespace App\Services\LegacyMigration;

use App\Models\DocumentSequence;
use App\Models\Floor;
use App\Models\LegacyMigrationCheckpoint;
use App\Models\LegacyMigrationError;
use App\Models\LegacyMigrationIdMap;
use App\Models\MenuType;
use Database\Seeders\DineTypeSeeder;
use Database\Seeders\PaymentMethodSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BusinessDataReset
{
    /**
     * Business tables cleared on every full refresh. System configuration is absent from this list.
     *
     * @var list<string>
     */
    public const TABLES = [
        'receipt_items',
        'receipts',
        'bill_payments',
        'bill_items',
        'bills',
        'order_transfers',
        'order_items',
        'table_cleaning_holds',
        'orders',
        'tables',
        'customers',
        'menu_items',
        'menu_categories',
        'discounts',
        'sessions',
        'personal_access_tokens',
        'users',
        'floors',
    ];

    /**
     * @return array<string, int>
     */
    public function counts(): array
    {
        $counts = [];

        foreach (self::TABLES as $table) {
            $counts[$table] = Schema::hasTable($table) ? DB::table($table)->count() : 0;
        }

        return $counts;
    }

    public function clear(): void
    {
        $driver = DB::getDriverName();
        Schema::disableForeignKeyConstraints();

        try {
            foreach (self::TABLES as $table) {
                if (! Schema::hasTable($table)) {
                    continue;
                }

                if ($driver === 'sqlite') {
                    DB::table($table)->delete();
                } else {
                    DB::statement('TRUNCATE TABLE `'.$table.'`');
                }
            }

            if ($driver === 'sqlite') {
                $sequence = DB::select("SELECT name FROM sqlite_master WHERE type = 'table' AND name = 'sqlite_sequence'");

                if ($sequence !== []) {
                    DB::table('sqlite_sequence')->whereIn('name', self::TABLES)->delete();
                }
            }
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        LegacyMigrationIdMap::query()->delete();
        LegacyMigrationCheckpoint::query()->delete();
        LegacyMigrationError::query()->delete();

        DB::table('document_sequences')->update(['next_number' => 1, 'updated_at' => now()]);

        $this->restoreConfiguration();
    }

    public function restoreConfiguration(): void
    {
        app(RoleSeeder::class)->run();
        app(PermissionSeeder::class)->run();
        app(RolePermissionSeeder::class)->run();
        app(DineTypeSeeder::class)->run();
        app(PaymentMethodSeeder::class)->run();
        MenuType::ensureDefaults();

        if (! Floor::query()->whereKey(1)->exists()) {
            $floor = new Floor;
            $floor->id = 1;
            $floor->name = 'Ground Floor';
            $floor->code = 'ground-floor';
            $floor->sort_order = 1;
            $floor->is_active = true;
            $floor->save();
        }

        if (! DocumentSequence::query()->exists()) {
            DocumentSequence::query()->insert([
                ['name' => DocumentSequence::ORDER, 'next_number' => 1, 'created_at' => now(), 'updated_at' => now()],
                ['name' => DocumentSequence::BILL, 'next_number' => 1, 'created_at' => now(), 'updated_at' => now()],
            ]);
        }
    }
}
