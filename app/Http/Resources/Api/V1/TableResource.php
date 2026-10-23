<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Order;
use App\Services\Billing\BillCalculationService;
use App\Services\Table\TableOccupancy;
use App\Services\Table\TableOccupancyService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TableResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $occupancy = $this->occupancySnapshot();
        $partitions = $this->partitionPayload();
        $merge = $this->mergePayload();

        return [
            'id' => $this->id,
            'number' => $this->number,
            'description' => $this->description,
            'capacity' => $this->capacity,
            'status' => $occupancy->status->value,
            'server_now' => now()->toIso8601String(),
            'floor_id' => $this->floor_id,
            'code' => $this->code,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
            'pax' => $this->when(
                $this->relationLoaded('currentOrder'),
                fn () => $this->currentOrder?->pax,
            ),
            'total' => $this->when(
                $this->relationLoaded('currentOrder'),
                fn () => $this->currentOrder
                    ? number_format($this->currentOrder->itemsTotal(), 2, '.', '')
                    : null,
            ),
            'occupancy' => $occupancy->toArray(),
            'partitions' => $partitions,
            'merge' => $merge,
            'current_order' => $this->when(
                $this->relationLoaded('currentOrder') && $this->currentOrder,
                fn () => new TableCurrentOrderResource($this->currentOrder),
            ),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array{
     *     is_merged: bool,
     *     is_merged_satellite: bool,
     *     is_merge_main: bool,
     *     merged_into_table_id: int|null,
     *     merged_into_display: string|null,
     *     merged_table_display: string|null
     * }
     */
    private function mergePayload(): array
    {
        $occupancy = app(TableOccupancyService::class);
        $host = $occupancy->hostOrderForSatellite($this->resource);
        $host?->loadMissing('table:id,number');

        $mainOrder = $occupancy->unreleasedOrders($this->resource)
            ->first(fn (Order $order): bool => $order->hasMergedTables() && $occupancy->isOccupiedPartition($order));
        $mainOrder?->loadMissing('table:id,number');

        $isSatellite = $host !== null && $mainOrder === null;

        return [
            'is_merged' => $isSatellite,
            'is_merged_satellite' => $isSatellite,
            'is_merge_main' => $mainOrder !== null,
            'merged_into_table_id' => $host?->table_id,
            'merged_into_display' => $host?->table?->number,
            'merged_table_display' => $host?->tableDisplayName() ?? $mainOrder?->tableDisplayName(),
        ];
    }

    private function occupancySnapshot(): TableOccupancy
    {
        return app(TableOccupancyService::class)->snapshot($this->resource);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function partitionPayload(): array
    {
        $occupancy = app(TableOccupancyService::class);
        $calculator = app(BillCalculationService::class);
        $orders = $occupancy->unreleasedOrders($this->resource);
        $orders->loadMissing(['customer', 'dineType', 'items', 'table:id,number']);

        $orderPartitions = $orders->map(function ($order) use ($occupancy, $calculator) {
            $state = $occupancy->isOccupiedPartition($order)
                ? 'occupied'
                : ($occupancy->isCleaningPartition($order) ? 'cleaning' : 'released');

            if ($state === 'released') {
                return null;
            }

            $customer = $order->relationLoaded('customer') ? $order->customer : null;
            $dineType = $order->relationLoaded('dineType') ? $order->dineType : null;
            $cleaningStarted = $state === 'cleaning' ? $occupancy->cleaningStartedAt($order) : null;
            $cleaningExpires = $state === 'cleaning' ? $occupancy->cleaningExpiresAt($order) : null;

            return [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'partition_label' => $order->partition_label,
                'display_label' => $order->tableDisplayName(),
                'pax' => $order->pax,
                'allocated_seats' => $occupancy->allocatedSeats($order),
                'state' => $state,
                'historical_customer_name' => $order->historical_customer_name,
                'customer' => $customer ? [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'phone' => $customer->phone,
                ] : null,
                'dine_type' => $dineType ? [
                    'id' => $dineType->id,
                    'name' => $dineType->name,
                    'slug' => $dineType->slug,
                    'requires_table' => $dineType->requiresTable(),
                    'requires_full_table' => $dineType->requiresFullTable(),
                ] : null,
                'amount' => $order->relationLoaded('items')
                    ? number_format($order->itemsTotal(), 2, '.', '')
                    : null,
                'checkout_eligible' => $state === 'occupied' && $calculator->isEligible($order),
                'cleaning_started_at' => $cleaningStarted?->toIso8601String(),
                'cleaning_expires_at' => $cleaningExpires?->toIso8601String(),
            ];
        })->filter()->values();

        $holdPartitions = $occupancy->activeCleaningHolds($this->resource)->map(function ($hold) {
            return [
                'order_id' => null,
                'cleaning_hold_id' => $hold->id,
                'order_number' => null,
                'partition_label' => null,
                'display_label' => $this->number,
                'pax' => 0,
                'allocated_seats' => (int) $hold->seats,
                'state' => 'cleaning',
                'customer' => null,
                'dine_type' => null,
                'amount' => null,
                'checkout_eligible' => false,
                'cleaning_started_at' => $hold->started_at?->toIso8601String(),
                'cleaning_expires_at' => $hold->expires_at?->toIso8601String(),
            ];
        });

        return $orderPartitions->concat($holdPartitions)->values()->all();
    }
}
