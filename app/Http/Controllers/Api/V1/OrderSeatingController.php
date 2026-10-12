<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\OrderResource;
use App\Http\Resources\Api\V1\TableResource;
use App\Models\Order;
use App\Models\Table;
use App\Services\Table\TableSeatingService;
use Illuminate\Http\JsonResponse;

class OrderSeatingController extends Controller
{
    public function __construct(
        private readonly TableSeatingService $seatingService,
    ) {}

    public function clean(Order $order): JsonResponse
    {
        $order = $this->seatingService->markClean($order);

        return response()->json([
            'success' => true,
            'message' => 'Seating marked clean.',
            'data' => new OrderResource($order),
        ]);
    }

    public function cleanTable(Table $table): JsonResponse
    {
        $table = $this->seatingService->markTableClean($table);

        return response()->json([
            'success' => true,
            'message' => 'Seating marked clean.',
            'data' => new TableResource($table),
        ]);
    }
}
