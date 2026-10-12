<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Order;
use App\Services\Table\TableOccupancyService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BillResource extends JsonResource
{
    /**
     * @param  array<string, mixed>|null  $calculation
     */
    public function __construct($resource, private readonly ?array $calculation = null)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $cgstPercent = $this->calculation['cgst_percent'] ?? (float) config('billing.cgst_percent');
        $sgstPercent = $this->calculation['sgst_percent'] ?? (float) config('billing.sgst_percent');
        $taxable = (float) $this->taxable_amount;
        $cgstAmount = $this->calculation['cgst_amount'] ?? round(($taxable * $cgstPercent) / 100, 2);
        $sgstAmount = $this->calculation['sgst_amount'] ?? round(($taxable * $sgstPercent) / 100, 2);

        $order = $this->whenLoaded('order');
        $table = $order && $order->relationLoaded('table') ? $order->table : null;
        $occupancy = $table
            ? app(TableOccupancyService::class)->snapshot($table)->toArray()
            : null;

        return [
            'id' => $this->id,
            'bill_number' => $this->bill_number,
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'subtotal' => number_format((float) $this->subtotal, 2, '.', ''),
            'discount_amount' => number_format((float) $this->discount_amount, 2, '.', ''),
            'taxable_amount' => number_format($taxable, 2, '.', ''),
            'tax_amount' => number_format((float) $this->tax_amount, 2, '.', ''),
            'round_off' => number_format((float) $this->round_off, 2, '.', ''),
            'grand_total' => number_format((float) $this->grand_total, 2, '.', ''),
            'gst' => [
                'cgst_percent' => number_format((float) $cgstPercent, 2, '.', ''),
                'sgst_percent' => number_format((float) $sgstPercent, 2, '.', ''),
                'cgst_amount' => number_format((float) $cgstAmount, 2, '.', ''),
                'sgst_amount' => number_format((float) $sgstAmount, 2, '.', ''),
            ],
            'issued_at' => $this->issued_at?->toIso8601String(),
            'settled_at' => $this->settled_at?->toIso8601String(),
            'order' => $order ? [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status instanceof \BackedEnum ? $order->status->value : $order->status,
            ] : null,
            'table' => $table ? [
                'id' => $table->id,
                'number' => $order instanceof Order
                    ? $order->tableDisplayName()
                    : $table->number,
                'status' => $table->status instanceof \BackedEnum ? $table->status->value : $table->status,
                'occupancy' => $occupancy,
            ] : null,
            'items' => BillItemResource::collection($this->whenLoaded('items')),
        ];
    }
}
