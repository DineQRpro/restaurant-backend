<?php

namespace App\Services\LegacyMigration\Migrators;

use App\Models\MenuCategory;
use App\Models\MenuType;
use App\Services\LegacyMigration\MigrationContext;
use App\Services\LegacyMigration\RowRejected;
use App\Services\LegacyMigration\Values;

class MenuCategoryMigrator extends Migrator
{
    public function key(): string
    {
        return 'menu-categories';
    }

    public function sourceTable(): string
    {
        return 'tbl_category';
    }

    public function primaryKey(): string
    {
        return 'category_id';
    }

    public function destinationTable(): string
    {
        return 'menu_categories';
    }

    protected function prepare(MigrationContext $context): void
    {
        $context->foodMenuTypeId = MenuType::query()
            ->where('slug', MenuType::SLUG_FOOD)
            ->where('is_active', true)
            ->value('id');
    }

    protected function import(MigrationContext $context, object $row, string $sourceId): void
    {
        if ($context->foodMenuTypeId === null) {
            throw RowRejected::failed('Food Menu is not available.');
        }

        $name = Values::required($row->category_title ?? null, 'Category name', 255);
        $description = Values::nullableText($row->category_desc ?? null);
        $image = Values::limited(Values::nullableText($row->category_image ?? null), 'Category image', 255);
        $createdAt = Values::timestamp($row->cat_created_at ?? null, 'Created at');
        $updatedAt = Values::timestamp($row->cat_updated_at ?? null, 'Updated at');

        if (! $context->persist) {
            $this->remember($context, $sourceId, null);

            return;
        }

        $category = new MenuCategory([
            'menu_type_id' => $context->foodMenuTypeId,
            'name' => $name,
            'description' => $description,
            'image' => $image,
            'sort_order' => 0,
            'is_active' => true,
        ]);
        $category->created_at = $createdAt;
        $category->updated_at = $updatedAt;
        $category->save();

        $this->remember($context, $sourceId, $category->id);
    }
}
