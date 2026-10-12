<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Orders\UpdateOrderMergeTablesRequest;
use App\Models\Order;
use App\Models\Table;
use App\Services\Table\TableMergeService;
use Illuminate\Http\JsonResponse;

class OrderMergeController extends Controller
{
    public function __construct(
        private readonly TableMergeService $mergeService,
    ) {}

    public function options(Order $order): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Merge table options retrieved successfully.',
            'data' => $this->mergeService->options($order, request()->user()),
        ]);
    }

    public function tableOptions(Table $table): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Merge table options retrieved successfully.',
            'data' => $this->mergeService->optionsForTable($table, request()->user()),
        ]);
    }

    public function update(UpdateOrderMergeTablesRequest $request, Order $order): JsonResponse
    {
        $result = $this->mergeService->sync($order, $request->user(), $request->tableIds());

        $message = $result['merged_tables'] === []
            ? 'Tables unmerged successfully.'
            : 'Tables merged successfully.';

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $result,
        ]);
    }
}
