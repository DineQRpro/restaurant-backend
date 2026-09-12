<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TableResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'description' => $this->description,
            'capacity' => $this->capacity,
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
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
            'current_order' => $this->when(
                $this->relationLoaded('currentOrder') && $this->currentOrder,
                fn () => new TableCurrentOrderResource($this->currentOrder),
            ),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
