<?php

namespace App\Http\Controllers\Admin\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Inventory\StockIndexRequest;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Services\Inventory\StockBalanceService;
use Illuminate\View\View;

class InventoryStockController extends Controller
{
    public function __construct(private readonly StockBalanceService $stock) {}

    public function index(StockIndexRequest $request): View
    {
        return view('admin.inventory.stock.index', [
            'items' => $this->stock->overview($request->filters()),
            'filters' => $request->filters(),
            'categories' => InventoryCategory::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function movements(StockIndexRequest $request): View
    {
        return view('admin.inventory.stock.movements', [
            'movements' => $this->stock->movements($request->filters()),
            'filters' => $request->filters(),
            'items' => InventoryItem::query()->orderBy('name')->get(['id', 'name', 'code']),
        ]);
    }

    public function low(StockIndexRequest $request): View
    {
        return view('admin.inventory.stock.low', [
            'items' => $this->stock->lowStock($request->filters()),
            'filters' => $request->filters(),
        ]);
    }
}
