<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TableCurrentOrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'pax' => $this->pax,
            'total' => number_format($this->itemsTotal(), 2, '.', ''),
            'started_at' => $this->started_at?->toIso8601String(),
            'customer' => new CustomerResource($this->whenLoaded('customer')),
            'dine_type' => new DineTypeResource($this->whenLoaded('dineType')),
        ];
    }
}
