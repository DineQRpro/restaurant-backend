<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Floors\SaveFloorLayoutRequest;
use App\Http\Resources\Api\V1\TableResource;
use App\Models\Floor;
use App\Services\Table\FloorLayoutService;
use App\Services\Table\TableService;
use Illuminate\Http\JsonResponse;

class FloorController extends Controller
{
    public function __construct(
        private readonly FloorLayoutService $layouts,
        private readonly TableService $tables,
    ) {}

    public function index(): JsonResponse
    {
        $floors = Floor::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Floors retrieved successfully.',
            'data' => $floors->map(fn (Floor $floor): array => $this->floorPayload($floor))->values(),
        ]);
    }

    public function show(Floor $floor): JsonResponse
    {
        $tables = $floor->tables()
            ->with($this->tables->currentOrderRelations())
            ->orderBy('sort_order')
            ->orderBy('number')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Floor layout retrieved successfully.',
            'data' => [
                'floor' => $this->floorPayload($floor),
                'tables' => TableResource::collection($tables)->resolve(),
            ],
        ]);
    }

    public function update(SaveFloorLayoutRequest $request, Floor $floor): JsonResponse
    {
        $saved = $this->layouts->save(
            $floor,
            $request->validated('placements'),
            (int) $request->validated('layout_version'),
        );

        return $this->show($saved);
    }

    /**
     * @return array<string, mixed>
     */
    private function floorPayload(Floor $floor): array
    {
        return [
            'id' => $floor->id,
            'name' => $floor->name,
            'code' => $floor->code,
            'sort_order' => $floor->sort_order,
            'is_active' => $floor->is_active,
            'layout_version' => (int) $floor->layout_version,
            'layout_columns' => (int) $floor->layout_columns,
            'layout_rows' => (int) $floor->layout_rows,
            'canvas' => [
                'columns' => (int) $floor->layout_columns,
                'rows' => (int) $floor->layout_rows,
                'width' => (int) $floor->layout_columns,
                'height' => (int) $floor->layout_rows,
                'grid' => 1,
            ],
        ];
    }
}
