<?php

namespace App\Services\LegacyMigration\Migrators;

use App\Enums\DietaryType;
use App\Models\MenuItem;
use App\Services\LegacyMigration\MigrationContext;
use App\Services\LegacyMigration\RowRejected;
use App\Services\LegacyMigration\Values;

class MenuItemMigrator extends Migrator
{
    public function key(): string
    {
        return 'menu-items';
    }

    public function sourceTable(): string
    {
        return 'tbl_products';
    }

    public function primaryKey(): string
    {
        return 'product_id';
    }

    public function destinationTable(): string
    {
        return 'menu_items';
    }

    protected function import(MigrationContext $context, object $row, string $sourceId): void
    {
        $categorySource = Values::text($row->product_category ?? null);

        if ($categorySource === '' || ! $context->mapped('tbl_category', $categorySource)) {
            throw RowRejected::failed('Menu category is not mapped.');
        }

        $categoryId = $context->destinationId('tbl_category', $categorySource);

        if ($context->persist && $categoryId === null) {
            throw RowRejected::failed('Menu category is not mapped.');
        }

        $name = Values::required($row->product_title ?? null, 'Item name', 255);
        $description = Values::nullableText($row->product_desc ?? null);
        $price = Values::nonNegativeDecimal($row->product_original_price ?? null, 'Price');
        $dietary = $this->dietary($row->product_main_category ?? null);
        $available = $this->availability($row->product_avail ?? null);
        $chefSpecial = $this->chefSpecial($row->chef_special ?? null);
        $spicy = $this->spice($row->product_spicy ?? null);
        $sortOrder = $this->priority($row->priority ?? null);
        $createdAt = Values::timestamp($row->created_at ?? null, 'Created at');
        $updatedAt = Values::timestamp($row->updated_at ?? null, 'Updated at');

        if (! $context->persist) {
            $this->remember($context, $sourceId, null);

            return;
        }

        $item = new MenuItem([
            'name' => $name,
            'description' => $description,
            'category_id' => $categoryId,
            'dietary_type' => $dietary,
            'price' => $price,
            'is_available' => $available,
            'is_chef_special' => $chefSpecial,
            'sort_order' => $sortOrder,
            'spicy_level' => $spicy,
        ]);
        $item->created_at = $createdAt;
        $item->updated_at = $updatedAt;
        $item->save();

        $this->remember($context, $sourceId, $item->id);
    }

    private function dietary(mixed $value): ?DietaryType
    {
        if ($value === null || $value === '') {
            return null;
        }

        return match ((string) $value) {
            '1' => DietaryType::Vegetarian,
            '2' => DietaryType::NonVegetarian,
            '3' => DietaryType::Eggetarian,
            default => throw RowRejected::failed('Dietary type is not supported.'),
        };
    }

    private function availability(mixed $value): bool
    {
        return match ((string) $value) {
            '1' => true,
            '2' => false,
            default => throw RowRejected::failed('Availability must be 1 or 2.'),
        };
    }

    private function chefSpecial(mixed $value): bool
    {
        if ($value === null || $value === '' || (string) $value === '0') {
            return false;
        }

        if ((string) $value === '1') {
            return true;
        }

        throw RowRejected::failed('Chef special must be empty, 0, or 1.');
    }

    private function spice(mixed $value): ?int
    {
        if ($value === null || $value === '' || (string) $value === '0') {
            return null;
        }

        if (in_array((string) $value, ['1', '2'], true)) {
            return (int) $value;
        }

        throw RowRejected::failed('Spice level is not supported.');
    }

    private function priority(mixed $value): int
    {
        if ($value === null || $value === '') {
            return 4;
        }

        $priority = Values::nonNegativeInteger($value, 'Sort order');

        return $priority;
    }
}
