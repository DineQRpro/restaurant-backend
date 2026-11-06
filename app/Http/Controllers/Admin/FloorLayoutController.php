<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\BusinessConflictException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FloorLayout\AssignFloorTableRequest;
use App\Http\Requests\Admin\FloorLayout\FloorDetailsRequest;
use App\Http\Requests\Api\V1\Floors\SaveFloorLayoutRequest;
use App\Models\Floor;
use App\Models\Table;
use App\Services\Table\FloorLayoutService;
use App\Services\Table\TableLayout;
use App\Services\Table\TableOccupancyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FloorLayoutController extends Controller
{
    public function __construct(
        private readonly FloorLayoutService $layouts,
        private readonly TableOccupancyService $occupancy,
    ) {}

    public function index(Request $request): View
    {
        $floors = Floor::query()->orderBy('sort_order')->orderBy('name')->get();
        $selected = $floors->firstWhere('id', $request->integer('floor')) ?? $floors->first();
        $tables = Table::query()
            ->with(['floor:id,name', 'unreleasedOrders'])
            ->orderBy('number')
            ->get();

        $statuses = [];
        foreach ($tables as $table) {
            $statuses[$table->id] = $this->occupancy->snapshot($table)->status->label();
        }

        $placed = $selected
            ? $tables->filter(fn (Table $table): bool => (int) $table->floor_id === (int) $selected->id && $table->layout_x !== null && $table->layout_y !== null)->values()
            : collect();

        return view('admin.floor-layout.index', [
            'floors' => $floors,
            'selected' => $selected,
            'tables' => $tables,
            'statuses' => $statuses,
            'placed' => $placed,
            'others' => $selected
                ? $tables->reject(fn (Table $table): bool => (int) $table->floor_id === (int) $selected->id && $table->layout_x !== null && $table->layout_y !== null)
                : $tables,
            'previewTables' => $placed->map(function (Table $table): array {
                $size = \App\Services\Table\TableLayout::size((int) $table->capacity);

                return [
                    'number' => $table->number,
                    'x' => (int) $table->layout_x,
                    'y' => (int) $table->layout_y,
                    'width' => $size['width'],
                    'height' => $size['height'],
                ];
            })->values(),
            'floorForm' => $selected ? [
                'name' => $selected->name,
                'description' => $selected->description,
                'code' => $selected->code,
                'sort_order' => $selected->sort_order,
                'is_active' => $selected->is_active ? 1 : 0,
                'rows' => $selected->layout_rows,
                'columns' => $selected->layout_columns,
            ] : null,
        ]);
    }

    public function store(FloorDetailsRequest $request): RedirectResponse
    {
        $floor = $this->layouts->create($request->validated());

        return redirect()
            ->route('admin.floor-layout.index', ['floor' => $floor->id])
            ->with('success', 'Floor created.');
    }

    public function update(FloorDetailsRequest $request, Floor $floor): RedirectResponse
    {
        $this->layouts->updateDetails($floor, $request->validated());

        return redirect()
            ->route('admin.floor-layout.index', ['floor' => $floor->id])
            ->with('success', 'Floor saved.');
    }

    public function save(SaveFloorLayoutRequest $request, Floor $floor): RedirectResponse
    {
        try {
            $this->layouts->save(
                $floor,
                $request->validated('placements'),
                (int) $request->validated('layout_version'),
            );
        } catch (BusinessConflictException $exception) {
            return redirect()
                ->route('admin.floor-layout.index', ['floor' => $floor->id])
                ->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('admin.floor-layout.index', ['floor' => $floor->id])
            ->with('success', 'Floor layout saved.');
    }

    public function assign(AssignFloorTableRequest $request, Floor $floor): RedirectResponse
    {
        $table = Table::query()->findOrFail($request->integer('table_id'));

        try {
            $this->layouts->assign(
                $floor,
                $table,
                $request->integer('x'),
                $request->integer('y'),
                $request->integer('layout_version'),
            );
        } catch (BusinessConflictException $exception) {
            return redirect()
                ->route('admin.floor-layout.index', ['floor' => $floor->id])
                ->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('admin.floor-layout.index', ['floor' => $floor->id])
            ->with('success', 'Table assigned. Orders and status were not changed.');
    }
}
