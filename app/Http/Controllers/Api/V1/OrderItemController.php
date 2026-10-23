<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\OrderItemStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Orders\UpdateOrderItemStatusRequest;
use App\Http\Resources\Api\V1\CustomerResource;
use App\Http\Resources\Api\V1\OrderItemResource;
use App\Models\Order;
use App\Models\Table;
use App\Services\Order\OrderItemService;
use App\Services\Table\TableMergeService;
use App\Services\Table\TableOccupancyService;
use App\Services\Table\TableTransferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

class OrderItemController extends Controller
{
    public function __construct(
        private readonly OrderItemService $orderItemService,
        private readonly TableMergeService $mergeService,
        private readonly TableTransferService $transferService,
        private readonly TableOccupancyService $occupancy,
    ) {}

    public function activeForTable(Table $table): JsonResponse
    {
        $result = $this->orderItemService->itemsForTable($table);
        $order = $result['order'];

        return response()->json([
            'success' => true,
            'message' => 'Active order items retrieved successfully.',
            'data' => $this->itemsPayload($order, $result['items']),
        ]);
    }

    public function forOrder(Order $order): JsonResponse
    {
        $result = $this->orderItemService->itemsForOrder($order);

        return response()->json([
            'success' => true,
            'message' => 'Order items retrieved successfully.',
            'data' => $this->itemsPayload($result['order'], $result['items']),
        ]);
    }

    public function updateStatus(UpdateOrderItemStatusRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $items = $this->orderItemService->updateStatuses(
            array_map('intval', $validated['order_item_ids']),
            OrderItemStatus::from($validated['status']),
            isset($validated['order_id']) ? (int) $validated['order_id'] : null,
        );

        return response()->json([
            'success' => true,
            'message' => 'Order item statuses updated successfully.',
            'data' => [
                'items' => OrderItemResource::collection($items)->resolve(),
            ],
        ]);
    }

    /**
     * @param  Collection<int, mixed>  $items
     * @return array<string, mixed>
     */
    private function itemsPayload(?Order $order, Collection $items): array
    {
        $user = request()->user();
        $canMerge = $order && $user ? $this->mergeService->canManage($order, $user) : false;
        $canTransfer = $order && $user ? $this->transferService->canTransfer($order, $user) : false;
        $order?->loadMissing('joinedInto.table');

        return [
            'order' => $order ? [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status instanceof \BackedEnum
                    ? $order->status->value
                    : $order->status,
                'pax' => $order->pax,
                'allocated_seats' => $this->occupancy->allocatedSeats($order),
                'table_id' => $order->table_id,
                'table_number' => $order->table?->number,
                'partition_label' => $order->partition_label,
                'display_label' => $order->tableDisplayName(),
                'can_merge' => $canMerge,
                'can_transfer' => $canTransfer,
                'has_merged_tables' => $order->hasMergedTables(),
                'merged_table_display' => $order->tableDisplayName(),
                'joined_into_order_id' => $order->joined_into_order_id,
                'joined_into_order_number' => $order->joinedInto?->order_number,
                'joined_into_table_id' => $order->joinedInto?->table_id,
                'historical_customer_name' => $order->historical_customer_name,
                'customer' => $order->customer
                    ? (new CustomerResource($order->customer))->resolve()
                    : null,
                'dine_type' => $order->dineType ? [
                    'id' => $order->dineType->id,
                    'name' => $order->dineType->name,
                    'slug' => $order->dineType->slug,
                    'requires_table' => $order->dineType->requiresTable(),
                    'requires_full_table' => $order->dineType->requiresFullTable(),
                ] : null,
            ] : null,
            'items' => OrderItemResource::collection($items)->resolve(),
        ];
    }
}
