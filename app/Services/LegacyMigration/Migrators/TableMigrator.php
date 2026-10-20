<?php

namespace App\Services\LegacyMigration\Migrators;

use App\Enums\TableOperationalStatus;
use App\Models\Table;
use App\Services\LegacyMigration\FloorResolver;
use App\Services\LegacyMigration\LegacySource;
use App\Services\LegacyMigration\MigrationContext;
use App\Services\LegacyMigration\RowRejected;
use App\Services\LegacyMigration\Values;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Str;

class TableMigrator extends Migrator
{
    public function __construct(
        LegacySource $source,
        private readonly FloorResolver $floors,
    ) {
        parent::__construct($source);
    }

    public function key(): string
    {
        return 'tables';
    }

    public function sourceTable(): string
    {
        return 'tbl_tables';
    }

    public function primaryKey(): string
    {
        return 'tables_id';
    }

    public function destinationTable(): string
    {
        return 'tables';
    }

    protected function prepare(MigrationContext $context): void
    {
        if ($context->freshDestination) {
            $context->floorId = 1;
            $context->floorBlocked = false;
            $context->floorMessage = 'Ground Floor id 1 is recreated after the reset.';

            return;
        }

        $this->floors->resolve($context);

        foreach (Table::query()->pluck('number') as $number) {
            $context->claim($context->tableNumbers, (string) $number);
        }
    }

    protected function constrain(Builder $query): void
    {
        $query->whereNull('tables_session_id');
    }

    protected function import(MigrationContext $context, object $row, string $sourceId): void
    {
        if ($context->floorBlocked || $context->floorId !== 1) {
            throw RowRejected::skipped($context->floorMessage ?? 'Ground Floor is not available.');
        }

        $number = Values::required($row->tables_number ?? null, 'Table number', 255);
        $description = Values::limited(Values::nullableText($row->tables_desc ?? null), 'Table description', 255);
        $capacity = Values::nonNegativeInteger($row->tables_chair_count ?? null, 'Table capacity');

        if ($capacity < 1 || $capacity > 255) {
            throw RowRejected::failed('Table capacity must be from 1 through 255.');
        }

        $createdAt = Values::timestamp($row->tab_created_at ?? null, 'Created at');
        $updatedAt = Values::timestamp($row->tab_updated_at ?? null, 'Updated at');

        if (! $context->claim($context->tableNumbers, $number)) {
            throw RowRejected::failed('Table number already exists.');
        }

        $metadata = [
            'tbl_tbid' => Values::nullableText($row->tbl_tbid ?? null),
        ];

        if (! $context->persist) {
            $this->remember($context, $sourceId, null, $metadata);

            return;
        }

        $table = new Table([
            'floor_id' => 1,
            'number' => $number,
            'description' => $description,
            'capacity' => $capacity,
            'status' => TableOperationalStatus::Available,
            'code' => (string) Str::ulid(),
            'sort_order' => 0,
            'is_active' => true,
        ]);
        $table->created_at = $createdAt;
        $table->updated_at = $updatedAt;
        $table->save();

        $this->remember($context, $sourceId, $table->id, $metadata);
    }
}
