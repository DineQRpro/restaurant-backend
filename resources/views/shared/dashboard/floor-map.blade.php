@php
    $positioned = $dashboard['tables']->filter(
        fn ($table) => $table->layout_x !== null && $table->layout_y !== null && $table->floor,
    )->groupBy('floor_id');
@endphp
@foreach ($positioned as $tables)
    @php
        $floor = $tables->first()->floor;
        $columns = max(1, (int) $floor->layout_columns);
        $rows = max(1, (int) $floor->layout_rows);
    @endphp
    <div class="mb-4 overflow-x-auto">
        <p class="mb-2 text-sm font-semibold text-[#1f2f3e]">{{ $floor->name }}</p>
        <div class="floor-plan-map" style="aspect-ratio: {{ $columns }} / {{ $rows }}; min-width: {{ $columns * 148 }}px;">
            @for ($row = 0; $row < $rows; $row++)
                @for ($column = 0; $column < $columns; $column++)
                    <span
                        class="floor-plan-cell {{ ($row + $column) % 2 === 0 ? 'is-alt' : '' }}"
                        style="left: calc({{ ($column / $columns) * 100 }}% + 4px); top: calc({{ ($row / $rows) * 100 }}% + 4px); width: calc({{ (1 / $columns) * 100 }}% - 8px); height: calc({{ (1 / $rows) * 100 }}% - 8px);"
                    ></span>
                @endfor
            @endfor
            @foreach ($tables as $table)
                @php
                    $occupancy = app(\App\Services\Table\TableOccupancyService::class)->snapshot($table);
                    $statusClass = match ($occupancy->status->label()) {
                        'Occupied' => 'floor-plan-status-occupied',
                        'Cleaning' => 'floor-plan-status-cleaning',
                        default => 'floor-plan-status-available',
                    };
                    $scaleClass = \App\Services\Table\TableLayout::illustrationClass((int) $table->capacity);
                @endphp
                <div
                    class="floor-plan-map-table"
                    style="left: calc({{ ($table->layout_x / $columns) * 100 }}% + 8px); top: calc({{ ($table->layout_y / $rows) * 100 }}% + 8px); width: calc({{ (1 / $columns) * 100 }}% - 16px); height: calc({{ (1 / $rows) * 100 }}% - 16px);"
                >
                    <div class="floor-plan-top {{ $statusClass }} {{ $scaleClass }} px-1 text-[10px] leading-tight">
                        <span class="block truncate">{{ $table->number }}</span>
                        <span class="block font-semibold opacity-90">{{ $table->capacity }} seats</span>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@endforeach
