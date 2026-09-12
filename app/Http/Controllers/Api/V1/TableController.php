<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Tables\IndexTableRequest;
use App\Http\Resources\Api\V1\TableResource;
use App\Models\Table;
use App\Services\Table\TableService;
use Illuminate\Http\JsonResponse;

class TableController extends Controller
{
    public function __construct(
        private readonly TableService $tableService,
    ) {}

    public function index(IndexTableRequest $request): JsonResponse
    {
        $filters = $request->validated();

        if ($request->exists('is_active')) {
            $filters['is_active'] = $request->boolean('is_active');
        }

        $tables = $this->tableService->paginate($filters);

        return response()->json([
            'success' => true,
            'message' => 'Tables retrieved successfully.',
            'data' => TableResource::collection($tables)->resolve(),
            'meta' => [
                'current_page' => $tables->currentPage(),
                'per_page' => $tables->perPage(),
                'total' => $tables->total(),
                'last_page' => $tables->lastPage(),
            ],
        ]);
    }

    public function show(Table $table): JsonResponse
    {
        $table->load($this->tableService->currentOrderRelations());

        return response()->json([
            'success' => true,
            'message' => 'Table retrieved successfully.',
            'data' => new TableResource($table),
        ]);
    }
}
