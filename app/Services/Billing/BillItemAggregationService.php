<?php

namespace App\Services\Billing;

use App\Models\Bill;
use App\Models\BillItem;

class BillItemAggregationService
{
    public function __construct(private readonly BillCalculationService $calculator) {}

    /**
     * Display rows for one bill. Original bill items are not changed.
     *
     * @return list<array{item_name: string, quantity: int, unit_price: float, packing_fee: float, line_total: float}>
     */
    public function lines(Bill $bill): array
    {
        $bill->loadMissing('items.orderItem');

        $groups = [];

        foreach ($bill->items as $item) {
            $key = $this->key($item);
            $lineTotal = $this->calculator->lineAmount(
                (float) $item->unit_price,
                (int) $item->quantity,
                (float) $item->packing_fee,
            );

            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'item_name' => (string) $item->item_name,
                    'quantity' => 0,
                    'unit_price' => round((float) $item->unit_price, 2),
                    'packing_fee' => 0.0,
                    'line_total' => 0.0,
                ];
            }

            $groups[$key]['quantity'] += (int) $item->quantity;
            $groups[$key]['packing_fee'] = round($groups[$key]['packing_fee'] + (float) $item->packing_fee, 2);
            $groups[$key]['line_total'] = round($groups[$key]['line_total'] + $lineTotal, 2);
        }

        return array_values($groups);
    }

    private function key(BillItem $item): string
    {
        $orderItem = $item->orderItem;
        $identity = $orderItem?->menu_item_id !== null
            ? 'item:'.$orderItem->menu_item_id
            : 'name:'.$this->normalize((string) $item->item_name);

        return implode('|', [
            $identity,
            number_format((float) $item->unit_price, 2, '.', ''),
            number_format((float) $item->packing_fee, 2, '.', ''),
            $this->normalize((string) ($orderItem?->special_instructions ?? '')),
        ]);
    }

    private function normalize(string $value): string
    {
        $value = trim(preg_replace('/\s+/', ' ', $value) ?? '');

        return mb_strtolower($value);
    }
}
