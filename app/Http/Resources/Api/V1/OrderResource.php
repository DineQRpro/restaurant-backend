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
            'customer' => new CustomerResource($this->whenLoaded('customer')),
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
