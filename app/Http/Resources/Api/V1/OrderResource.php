<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'pax' => $this->pax,
            'allocated_seats' => $this->allocated_seats,
            'partition_label' => $this->partition_label,
            'display_label' => $this->tableDisplayName(),
            'seating_cleaned_at' => $this->seating_cleaned_at?->toIso8601String(),
            'historical_customer_name' => $this->historical_customer_name,
            'customer' => $this->when(
                $this->relationLoaded('customer'),
                fn () => $this->customer ? new CustomerResource($this->customer) : null,
            ),
            'dine_type' => new DineTypeResource($this->whenLoaded('dineType')),
            'table' => $this->when(
                $this->relationLoaded('table'),
                fn () => $this->table ? new TableResource($this->table) : null,
            ),
            'waiter' => $this->whenLoaded('waiter', fn () => [
                'id' => $this->waiter->id,
                'name' => $this->waiter->name,
                'username' => $this->waiter->username,
            ]),
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'started_at' => $this->started_at?->toIso8601String(),
        ];
    }
}
