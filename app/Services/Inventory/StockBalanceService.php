<?php

namespace App\Services\Inventory;

use App\Models\InventoryItem;
use App\Models\InventoryStockMovement;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class StockBalanceService
{
    public function forItem(int $itemId): string
    {
        $balance = InventoryStockMovement::query()
            ->where('inventory_item_id', $itemId)
            ->selectRaw('COALESCE(SUM(quantity_in), 0) - COALESCE(SUM(quantity_out), 0) as balance')
            ->value('balance');

        return InventoryMoney::quantity($balance);
    }

    /**
     * @param  array{search?: string, category_id?: int|string|null, low_stock?: bool|string|null}  $filters
     */
    public function overview(array $filters): LengthAwarePaginator
    {
        return $this->itemQuery($filters)
            ->orderBy('inventory_items.name')
            ->paginate(20)
            ->withQueryString();
    }

    /**
     * @param  array{search?: string}  $filters
     */
    public function lowStock(array $filters): LengthAwarePaginator
    {
        return $this->itemQuery($filters)
            ->where('inventory_items.is_active', true)
            ->whereRaw('COALESCE(balances.balance, 0) <= inventory_items.reorder_level')
            ->orderByRaw('(inventory_items.reorder_level - COALESCE(balances.balance, 0)) desc')
            ->orderBy('inventory_items.name')
            ->paginate(20)
            ->withQueryString();
    }

    /**
     * @param  array{q?: string, scope?: string}  $filters
     * @return list<array<string, mixed>>
     */
    public function suggestItems(array $filters): array
    {
        $scope = ($filters['scope'] ?? 'low') === 'all' ? 'all' : 'low';
        $query = $this->itemQuery(['search' => $filters['q'] ?? null])
            ->where('inventory_items.is_active', true);

        if ($scope === 'low') {
            $query->whereRaw('COALESCE(balances.balance, 0) <= inventory_items.reorder_level')
                ->orderByRaw('(inventory_items.reorder_level - COALESCE(balances.balance, 0)) desc');
        } else {
            $query->orderByRaw('case when COALESCE(balances.balance, 0) <= inventory_items.reorder_level then 0 else 1 end')
                ->orderByRaw('(inventory_items.reorder_level - COALESCE(balances.balance, 0)) desc');
        }

        return $query
            ->orderBy('inventory_items.name')
            ->limit(20)
            ->get()
            ->map(fn (InventoryItem $item): array => $this->present($item))
            ->all();
    }

    /**
     * @param  array{search?: string, item_id?: int|string|null, receipt_id?: int|string|null}  $filters
     */
    public function movements(array $filters): LengthAwarePaginator
    {
        return InventoryStockMovement::query()
            ->with(['item', 'receipt', 'poster'])
            ->when($filters['item_id'] ?? null, fn (Builder $query, $itemId) => $query->where('inventory_item_id', $itemId))
            ->when($filters['receipt_id'] ?? null, fn (Builder $query, $receiptId) => $query->where('goods_receipt_id', $receiptId))
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $inner) use ($search): void {
                    $inner->whereHas('item', fn (Builder $items) => $items->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"))
                        ->orWhereHas('receipt', fn (Builder $receipts) => $receipts->where('receipt_number', 'like', "%{$search}%"));
                });
            })
            ->orderByDesc('posted_at')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();
    }

    /**
     * @param  array{search?: string|null, category_id?: int|string|null}  $filters
     */
    private function itemQuery(array $filters): Builder
    {
        $balances = InventoryStockMovement::query()
            ->select('inventory_item_id')
            ->selectRaw('SUM(quantity_in) - SUM(quantity_out) as balance')
            ->groupBy('inventory_item_id');

        return InventoryItem::query()
            ->with(['category', 'unit'])
            ->leftJoinSub($balances, 'balances', 'balances.inventory_item_id', '=', 'inventory_items.id')
            ->select('inventory_items.*')
            ->selectRaw('COALESCE(balances.balance, 0) as current_stock')
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $inner) use ($search): void {
                    $inner->where('inventory_items.name', 'like', "%{$search}%")
                        ->orWhere('inventory_items.code', 'like', "%{$search}%");
                });
            })
            ->when($filters['category_id'] ?? null, fn (Builder $query, $categoryId) => $query->where('inventory_items.inventory_category_id', $categoryId));
    }

    /**
     * @return array<string, mixed>
     */
    private function present(InventoryItem $item): array
    {
        $balance = InventoryMoney::quantity($item->getAttribute('current_stock'));
        $reorder = InventoryMoney::quantity($item->reorder_level);
        $shortage = bcsub($reorder, $balance, 3);

        return [
            'id' => $item->id,
            'code' => $item->code,
            'name' => $item->name,
            'category' => $item->category?->name,
            'unit' => $item->unit?->name,
            'symbol' => $item->unit?->symbol,
            'reorder_level' => $reorder,
            'maximum_level' => InventoryMoney::quantity($item->maximum_level),
            'current_stock' => $balance,
            'shortage' => bccomp($shortage, '0', 3) > 0 ? $shortage : '0.000',
            'low_stock' => bccomp($balance, $reorder, 3) <= 0,
        ];
    }
}
