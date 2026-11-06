<?php

namespace App\Services\Inventory;

use App\Enums\GoodsReceiptPaymentStatus;
use App\Enums\GoodsReceiptStatus;
use App\Exceptions\BusinessConflictException;
use App\Models\GoodsReceipt;
use App\Models\InventoryItem;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GoodsReceiptService
{
    public function __construct(private readonly GoodsReceiptNumberAllocator $numbers) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        return GoodsReceipt::query()
            ->with('vendor')
            ->when($filters['receipt_number'] ?? null, fn (Builder $query, string $number) => $query->where('receipt_number', 'like', "%{$number}%"))
            ->when($filters['vendor_id'] ?? null, fn (Builder $query, $vendorId) => $query->where('vendor_id', $vendorId))
            ->when($filters['vendor_bill_number'] ?? null, function (Builder $query, string $number): void {
                [, $key] = VendorBillNumber::normalize($number);
                $query->where('vendor_bill_key', 'like', '%'.$key.'%');
            })
            ->when($filters['from'] ?? null, fn (Builder $query, string $from) => $query->whereDate('receipt_date', '>=', $from))
            ->when($filters['to'] ?? null, fn (Builder $query, string $to) => $query->whereDate('receipt_date', '<=', $to))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['payment_status'] ?? null, fn (Builder $query, string $status) => $query->where('payment_status', $status))
            ->orderByDesc('receipt_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();
    }

    /**
     * @return list<array{id: int, name: string, code: string, label: string}>
     */
    public function suggestVendors(string $query): array
    {
        return Vendor::query()
            ->where('is_active', true)
            ->when($query !== '', function (Builder $builder) use ($query): void {
                $builder->where(function (Builder $inner) use ($query): void {
                    $inner->where('name', 'like', "%{$query}%")
                        ->orWhere('code', 'like', "%{$query}%");
                });
            })
            ->orderBy('name')
            ->limit(20)
            ->get()
            ->map(fn (Vendor $vendor): array => [
                'id' => $vendor->id,
                'name' => $vendor->name,
                'code' => $vendor->code,
                'label' => $vendor->name.' — '.$vendor->code,
            ])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $user): GoodsReceipt
    {
        try {
            return DB::transaction(function () use ($data, $user): GoodsReceipt {
                $receipt = $this->fill(new GoodsReceipt, $data, $user, true);
                $receipt->save();
                $receipt->lines()->createMany($this->lineRows($data['lines']));

                return $receipt->load('lines');
            }, 5);
        } catch (QueryException $exception) {
            $this->reraiseDuplicateBill($exception);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(GoodsReceipt $receipt, array $data): GoodsReceipt
    {
        try {
            return DB::transaction(function () use ($receipt, $data): GoodsReceipt {
                $locked = GoodsReceipt::query()->whereKey($receipt->id)->lockForUpdate()->firstOrFail();
                $this->assertDraft($locked);
                $this->fill($locked, $data, null, false);
                $locked->save();
                $locked->lines()->delete();
                $locked->lines()->createMany($this->lineRows($data['lines']));

                return $locked->load('lines');
            });
        } catch (QueryException $exception) {
            $this->reraiseDuplicateBill($exception);
        }
    }

    public function delete(GoodsReceipt $receipt): void
    {
        DB::transaction(function () use ($receipt): void {
            $locked = GoodsReceipt::query()->whereKey($receipt->id)->lockForUpdate()->firstOrFail();
            $this->assertDraft($locked);
            $locked->lines()->delete();
            $locked->delete();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>  $lines
     */
    public function priced(array $data): array
    {
        $rows = $this->lineRows($data['lines']);
        $header = InventoryMoney::header($rows);
        $paid = InventoryMoney::money($data['paid_amount'] ?? '0');
        InventoryMoney::assertPayment(GoodsReceiptPaymentStatus::from($data['payment_status']), $paid, $header['grand_total']);

        return [
            'rows' => $rows,
            'header' => $header,
            'paid_amount' => $paid,
            'outstanding_amount' => InventoryMoney::outstanding($header['grand_total'], $paid),
            'has_zero_price' => collect($rows)->contains(fn (array $row): bool => bccomp($row['unit_price'], '0.00', 2) === 0),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function fill(GoodsReceipt $receipt, array $data, ?User $creator, bool $assignNumber): GoodsReceipt
    {
        $priced = $this->priced($data);
        $vendor = Vendor::query()->whereKey($data['vendor_id'])->where('is_active', true)->first();

        if ($vendor === null) {
            throw new BusinessConflictException('Choose an active vendor.');
        }

        [$display, $key] = VendorBillNumber::normalize((string) $data['vendor_bill_number']);

        if ($display === '') {
            throw new BusinessConflictException('Vendor bill number is required.');
        }

        if ($assignNumber) {
            $receipt->receipt_number = $this->numbers->next();
            $receipt->idempotency_key = (string) Str::uuid();
            $receipt->status = GoodsReceiptStatus::Draft;
            $receipt->created_by = $creator?->id;
        }

        $receipt->vendor_id = $vendor->id;
        $receipt->vendor_code_snapshot = $vendor->code;
        $receipt->vendor_name_snapshot = $vendor->name;
        $receipt->vendor_bill_number = $display;
        $receipt->vendor_bill_key = $key;
        $receipt->vendor_bill_date = $data['vendor_bill_date'];
        $receipt->receipt_date = $data['receipt_date'];
        $receipt->payment_status = $data['payment_status'];
        $receipt->subtotal = $priced['header']['subtotal'];
        $receipt->gst_total = $priced['header']['gst_total'];
        $receipt->grand_total = $priced['header']['grand_total'];
        $receipt->paid_amount = $priced['paid_amount'];
        $receipt->outstanding_amount = $priced['outstanding_amount'];
        $receipt->notes = $data['notes'] ?? null;

        return $receipt;
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @return list<array<string, mixed>>
     */
    private function lineRows(array $lines): array
    {
        if ($lines === []) {
            throw new BusinessConflictException('Add at least one receipt line.');
        }

        $items = InventoryItem::query()
            ->with(['category', 'unit'])
            ->whereIn('id', collect($lines)->pluck('inventory_item_id')->filter()->all())
            ->get()
            ->keyBy('id');

        $rows = [];

        foreach ($lines as $line) {
            $item = $items->get((int) ($line['inventory_item_id'] ?? 0));

            if ($item === null || ! $item->is_active) {
                throw new BusinessConflictException('Each line must use an active inventory item.');
            }

            $quantity = InventoryMoney::quantity($line['quantity'] ?? '0');
            $price = InventoryMoney::money($line['unit_price'] ?? '0');
            $rate = InventoryMoney::money($line['gst_rate'] ?? '0');

            if (bccomp($quantity, '0.000', 3) <= 0) {
                throw new BusinessConflictException('Receipt quantity must be greater than zero.');
            }

            if (bccomp($price, '0.00', 2) < 0 || bccomp($rate, '0.00', 2) < 0 || bccomp($rate, '100.00', 2) > 0) {
                throw new BusinessConflictException('Unit price and GST rate must stay within the allowed range.');
            }

            $amounts = InventoryMoney::line($quantity, $price, $rate);

            $rows[] = [
                'inventory_item_id' => $item->id,
                'item_code_snapshot' => $item->code,
                'item_name_snapshot' => $item->name,
                'category_name_snapshot' => $item->category?->name ?? '',
                'unit_name_snapshot' => $item->unit?->name ?? '',
                'unit_symbol_snapshot' => $item->unit?->symbol ?? '',
                'quantity' => $quantity,
                'unit_price' => $price,
                'gst_rate' => $rate,
                ...$amounts,
                'batch_number' => filled($line['batch_number'] ?? null) ? $line['batch_number'] : null,
                'expiry_date' => filled($line['expiry_date'] ?? null) ? $line['expiry_date'] : null,
            ];
        }

        return $rows;
    }

    private function assertDraft(GoodsReceipt $receipt): void
    {
        if ($receipt->status !== GoodsReceiptStatus::Draft) {
            throw new BusinessConflictException('Posted and voided receipts cannot be changed or deleted.');
        }
    }

    private function reraiseDuplicateBill(QueryException $exception): never
    {
        $message = strtolower($exception->getMessage());
        $duplicate = str_contains($message, 'goods_receipts.vendor_id, goods_receipts.vendor_bill_key')
            || str_contains($message, 'vendor_id_vendor_bill_key');

        if ($duplicate) {
            throw new BusinessConflictException('This vendor bill number is already used for the selected vendor.');
        }

        throw $exception;
    }
}
