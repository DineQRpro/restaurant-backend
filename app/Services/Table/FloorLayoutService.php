<?php

namespace App\Services\Table;

use App\Exceptions\BusinessConflictException;
use App\Models\Floor;
use App\Models\Table;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FloorLayoutService
{
    /**
     * Replace every saved position on this floor.
     * Tables assigned to the floor and omitted from $placements become unplaced.
     * Tables on other floors are not changed. Call this with the full set of
     * positions the admin intends to keep.
     *
     * @param  list<array{table_id: int, x: int, y: int}>  $placements
     */
    public function save(Floor $floor, array $placements, int $expectedVersion): Floor
    {
        return DB::transaction(function () use ($floor, $placements, $expectedVersion): Floor {
            $locked = Floor::query()->whereKey($floor->id)->lockForUpdate()->firstOrFail();

            if ((int) $locked->layout_version !== $expectedVersion) {
                throw new BusinessConflictException('This floor layout was saved by someone else. Reload the floor and try again.');
            }

            $tables = Table::query()->where('floor_id', $locked->id)->lockForUpdate()->get()->keyBy('id');
            $placed = $this->normalizedPlacements($locked, $placements, $tables);

            Table::query()->where('floor_id', $locked->id)->update([
                'layout_x' => null,
                'layout_y' => null,
            ]);

            foreach ($placed as $placement) {
                Table::query()->whereKey($placement['table']->id)->update([
                    'layout_x' => $placement['x'],
                    'layout_y' => $placement['y'],
                ]);
            }

            $locked->layout_version = $expectedVersion + 1;
            $locked->save();

            return $locked->fresh();
        });
    }

    /**
     * @param  array{name: string, code?: string|null, description?: string|null, sort_order?: int, is_active?: bool, layout_columns: int, layout_rows: int}  $attributes
     */
    public function create(array $attributes): Floor
    {
        return Floor::query()->create([
            'name' => $attributes['name'],
            'code' => $this->uniqueCode((string) (($attributes['code'] ?? '') ?: $attributes['name'])),
            'description' => $attributes['description'] ?? null,
            'sort_order' => array_key_exists('sort_order', $attributes) && $attributes['sort_order'] !== null
                ? (int) $attributes['sort_order']
                : ((int) Floor::query()->max('sort_order')) + 1,
            'is_active' => (bool) ($attributes['is_active'] ?? true),
            'layout_columns' => (int) $attributes['layout_columns'],
            'layout_rows' => (int) $attributes['layout_rows'],
            'layout_version' => 1,
        ]);
    }

    /**
     * @param  array{name: string, code?: string|null, description?: string|null, sort_order?: int, is_active?: bool, layout_columns: int, layout_rows: int}  $attributes
     */
    public function updateDetails(Floor $floor, array $attributes): Floor
    {
        return DB::transaction(function () use ($floor, $attributes): Floor {
            $locked = Floor::query()->whereKey($floor->id)->lockForUpdate()->firstOrFail();
            $columns = (int) $attributes['layout_columns'];
            $rows = (int) $attributes['layout_rows'];
            $this->assertPlacementsFit($locked, $columns, $rows);

            $locked->fill([
                'name' => $attributes['name'],
                'code' => $this->uniqueCode((string) (($attributes['code'] ?? '') ?: $attributes['name']), $locked->id),
                'description' => $attributes['description'] ?? null,
                'sort_order' => (int) ($attributes['sort_order'] ?? 0),
                'is_active' => (bool) ($attributes['is_active'] ?? false),
                'layout_columns' => $columns,
                'layout_rows' => $rows,
            ]);

            if ($locked->isDirty('layout_columns') || $locked->isDirty('layout_rows')) {
                $locked->layout_version = (int) $locked->layout_version + 1;
            }

            $locked->save();

            return $locked->fresh();
        });
    }

    public function assign(Floor $floor, Table $table, int $x, int $y, int $expectedVersion): Floor
    {
        return DB::transaction(function () use ($floor, $table, $x, $y, $expectedVersion): Floor {
            $locked = Floor::query()->whereKey($floor->id)->lockForUpdate()->firstOrFail();

            if ((int) $locked->layout_version !== $expectedVersion) {
                throw new BusinessConflictException('This floor layout was saved by someone else. Reload the floor and try again.');
            }

            $lockedTable = Table::query()->whereKey($table->id)->lockForUpdate()->firstOrFail();
            $previousFloorId = $lockedTable->floor_id;

            if ($x < 0 || $y < 0 || $x >= (int) $locked->layout_columns || $y >= (int) $locked->layout_rows) {
                throw ValidationException::withMessages([
                    'x' => 'The table must stay inside this floor grid.',
                ]);
            }

            $occupied = Table::query()
                ->where('floor_id', $locked->id)
                ->whereKeyNot($lockedTable->id)
                ->where('layout_x', $x)
                ->where('layout_y', $y)
                ->lockForUpdate()
                ->exists();

            if ($occupied) {
                throw ValidationException::withMessages([
                    'x' => 'Two tables cannot share the same cell.',
                ]);
            }

            $lockedTable->forceFill([
                'floor_id' => $locked->id,
                'layout_x' => $x,
                'layout_y' => $y,
            ])->save();

            $locked->layout_version = $expectedVersion + 1;
            $locked->save();

            if ($previousFloorId !== null && (int) $previousFloorId !== (int) $locked->id) {
                Floor::query()->whereKey($previousFloorId)->lockForUpdate()->increment('layout_version');
            }

            return $locked->fresh();
        });
    }

    private function assertPlacementsFit(Floor $floor, int $columns, int $rows): void
    {
        $blocked = [];

        foreach ($floor->tables()->whereNotNull('layout_x')->whereNotNull('layout_y')->get() as $table) {
            if ((int) $table->layout_x >= $columns || (int) $table->layout_y >= $rows) {
                $blocked[] = $table->number;
            }
        }

        if ($blocked !== []) {
            throw ValidationException::withMessages([
                'layout_columns' => 'These placed tables would fall outside the new grid: '.implode(', ', $blocked).'. Move them before changing the grid.',
            ]);
        }
    }

    private function uniqueCode(string $source, ?int $ignoreId = null): string
    {
        $base = Str::slug($source);
        $base = $base !== '' ? substr($base, 0, 80) : 'floor';
        $code = $base;
        $suffix = 2;

        while (Floor::query()
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->where('code', $code)
            ->exists()) {
            $code = $base.'-'.$suffix;
            $suffix++;
        }

        return $code;
    }

    /**
     * @param  list<array{table_id: int, x: int, y: int}>  $placements
     * @param  Collection<int, Table>  $tables
     * @return list<array{table: Table, x: int, y: int}>
     */
    private function normalizedPlacements(Floor $floor, array $placements, Collection $tables): array
    {
        $normalized = [];
        $seen = [];

        foreach ($placements as $index => $placement) {
            $tableId = (int) $placement['table_id'];
            $table = $tables->get($tableId);

            if ($table === null) {
                throw ValidationException::withMessages([
                    "placements.$index.table_id" => 'The table is not assigned to this floor.',
                ]);
            }

            if (isset($seen[$tableId])) {
                throw ValidationException::withMessages([
                    "placements.$index.table_id" => 'A table can only be placed once.',
                ]);
            }

            $seen[$tableId] = true;
            $x = (int) $placement['x'];
            $y = (int) $placement['y'];

            if ($x < 0 || $y < 0 || $x >= (int) $floor->layout_columns || $y >= (int) $floor->layout_rows) {
                throw ValidationException::withMessages([
                    "placements.$index.x" => 'The table must stay inside the floor.',
                ]);
            }

            $normalized[] = [
                'table' => $table,
                'x' => $x,
                'y' => $y,
            ];
        }

        foreach ($normalized as $index => $placement) {
            foreach ($normalized as $otherIndex => $other) {
                if ($otherIndex <= $index) {
                    continue;
                }

                if ($placement['x'] === $other['x'] && $placement['y'] === $other['y']) {
                    throw ValidationException::withMessages([
                        "placements.$index.x" => 'Two tables cannot share the same cell.',
                    ]);
                }
            }
        }

        return $normalized;
    }
}
