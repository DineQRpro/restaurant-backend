<?php

namespace App\Services\Receipt;

use App\Models\Receipt;
use App\Models\ReceiptItem;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ReceiptService
{
    /**
     * @param  array{
     *     sdate?: string,
     *     edate?: string,
     *     receipt_no?: string,
     *     per_page?: int
     * }  $filters
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = Receipt::query();

        if (($filters['sdate'] ?? '') !== '') {
            $query->whereDate('receipt_date', '>=', $filters['sdate']);
        }

        if (($filters['edate'] ?? '') !== '') {
            $query->whereDate('receipt_date', '<=', $filters['edate']);
        }

        if (($filters['receipt_no'] ?? '') !== '') {
            $query->where('id', 'like', '%'.$filters['receipt_no'].'%');
        }

        $perPage = (int) ($filters['per_page'] ?? 20);

        return $query
            ->orderByDesc('receipt_date')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array{item_name: string, quantity: float, rate_price: float}>  $items
     */
    public function create(array $data, array $items): Receipt
    {
        return DB::transaction(function () use ($data, $items): Receipt {
            $receipt = Receipt::query()->create($this->headerPayload($data));
            $this->replaceItems($receipt, $items);

            return $receipt->fresh(['items']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array{item_name: string, quantity: float, rate_price: float}>  $items
     */
    public function update(Receipt $receipt, array $data, array $items): Receipt
    {
        return DB::transaction(function () use ($receipt, $data, $items): Receipt {
            $receipt->update($this->headerPayload($data));
            $this->replaceItems($receipt, $items);

            return $receipt->fresh(['items']);
        });
    }

    /**
     * @return array{
     *     receipt: Receipt,
     *     restaurant: array{name: string, address: string, phone: string, gstin: string},
     *     itemsTotal: float,
     *     gstAmount: float,
     *     grandTotal: float
     * }
     */
    public function printViewData(Receipt $receipt): array
    {
        $receipt->load(['items']);
        $print = config('restaurant.print', []);
        $name = trim((string) ($print['name'] ?? ''));
        $address = trim((string) ($print['address'] ?? ''));

        return [
            'receipt' => $receipt,
            'restaurant' => [
                'name' => $name !== '' ? ucwords($name) : '',
                'address' => $address !== '' ? ucwords($address) : '',
                'phone' => trim((string) ($print['phone'] ?? '')),
                'gstin' => trim((string) ($print['gstin'] ?? '')),
            ],
            'itemsTotal' => $receipt->itemsTotal(),
            'gstAmount' => $receipt->gstAmount(),
            'grandTotal' => $receipt->grandTotal(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function headerPayload(array $data): array
    {
        return [
            'receipt_date' => $data['receipt_date'],
            'customer_name' => $data['customer_name'],
            'mobile' => $data['mobile'],
            'program' => $data['program'],
            'other_instructions' => $data['other_instructions'] ?? null,
            'pax_count' => (int) $data['pax_count'],
            'advance_paid' => round((float) $data['advance_paid'], 2),
            'balance' => round((float) $data['balance'], 2),
            'include_gst' => (bool) ($data['include_gst'] ?? false),
        ];
    }

    /**
     * @param  list<array{item_name: string, quantity: float, rate_price: float}>  $items
     */
    private function replaceItems(Receipt $receipt, array $items): void
    {
        $receipt->items()->delete();

        foreach ($items as $item) {
            $quantity = (float) $item['quantity'];
            $rate = (float) $item['rate_price'];

            ReceiptItem::query()->create([
                'receipt_id' => $receipt->id,
                'item_name' => $item['item_name'],
                'quantity' => $quantity,
                'rate_price' => $rate,
                'amount' => round($quantity * $rate, 2),
            ]);
        }
    }
}
