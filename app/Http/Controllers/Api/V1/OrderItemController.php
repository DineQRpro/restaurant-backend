<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\OrderItemStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Orders\UpdateOrderItemStatusRequest;
use App\Http\Resources\Api\V1\CustomerResource;
use App\Http\Resources\Api\V1\OrderItemResource;
use App\Models\Table;
use App\Services\Order\OrderItemService;
use Illuminate\Http\JsonResponse;

class OrderItemController extends Controller
{
    public function __construct(
        private readonly OrderItemService $orderItemService,
    ) {}

    public function activeForTable(Table $table): JsonResponse
    {
        $result = $this->orderItemService->itemsForTable($table);
        $order = $result['order'];

        return response()->json([
            'success' => true,
            'message' => 'Active order items retrieved successfully.',
            'data' => [
                'order' => $order ? [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'status' => $order->status instanceof \BackedEnum
                        ? $order->status->value
                        : $order->status,
                    'pax' => $order->pax,
                    'customer' => $order->customer
                        ? (new CustomerResource($order->customer))->resolve()
                        : null,
                ] : null,
                'items' => OrderItemResource::collection($result['items'])->resolve(),
            ],
        ]);
    }

    public function updateStatus(UpdateOrderItemStatusRequest $request): JsonResponse
    {
        $items = $this->orderItemService->updateStatuses(
            array_map('intval', $request->validated('order_item_ids')),
            OrderItemStatus::from($request->validated('status')),
        );

        return response()->json([
            'success' => true,
            'message' => 'Order item statuses updated successfully.',
            'data' => [
                'items' => OrderItemResource::collection($items)->resolve(),
            ],
        ]);
    }
}
