<?php

use App\Http\Controllers\Admin\BillPaymentController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\FloorLayoutController;
use App\Http\Controllers\Admin\DiscountController;
use App\Http\Controllers\Admin\Inventory\GoodsReceiptController;
use App\Http\Controllers\Admin\Inventory\InventoryCategoryController;
use App\Http\Controllers\Admin\Inventory\InventoryItemController;
use App\Http\Controllers\Admin\Inventory\InventoryStockController;
use App\Http\Controllers\Admin\Inventory\UnitOfMeasureController;
use App\Http\Controllers\Admin\Inventory\VendorController;
use App\Http\Controllers\Admin\KotController;
use App\Http\Controllers\Admin\Menu\MenuCategoryController;
use App\Http\Controllers\Admin\Menu\MenuItemController;
use App\Http\Controllers\Admin\OrderBillController;
use App\Http\Controllers\Admin\PasswordController;
use App\Http\Controllers\Admin\ReceiptController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\TableController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\AccountPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Services\Auth\PosLoginService;
use App\Http\Controllers\Counter\BillPaymentController as CounterBillPaymentController;
use App\Http\Controllers\Counter\DashboardController as CounterDashboardController;
use App\Http\Controllers\Counter\KotController as CounterKotController;
use App\Http\Controllers\Counter\OrderBillController as CounterOrderBillController;
use App\Http\Controllers\Counter\PasswordController as CounterPasswordController;
use App\Http\Controllers\Counter\ReportController as CounterReportController;
use App\Http\Controllers\Counter\TakeawayOrderController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    $dashboard = auth()->check()
        ? app(PosLoginService::class)->dashboardUrl(auth()->user())
        : null;

    return redirect()->to($dashboard ?? route('login'));
});

Route::middleware('guest')->group(function () {
    Route::get('/auth/login', [LoginController::class, 'create'])->name('login');
    Route::post('/auth/login', [LoginController::class, 'store']);
    Route::get('/login', fn () => redirect()->route('login'));
    Route::get('/auth/change-password', [PasswordController::class, 'create'])->name('password.change');
    Route::post('/auth/change-password', [PasswordController::class, 'store'])->name('password.change.store');
    Route::get('/auth/password', fn () => redirect()->route('password.change'))->name('password.edit');
    Route::post('/auth/password', [PasswordController::class, 'store'])->name('password.update');
});

Route::middleware('auth')->group(function () {
    Route::get('/auth/account/password', [AccountPasswordController::class, 'edit'])->name('account.password.edit');
    Route::post('/auth/account/password', [AccountPasswordController::class, 'update'])->name('account.password.update');
});

Route::get('/auth/unavailable', [LoginController::class, 'unavailable'])->name('auth.unavailable');

Route::post('/auth/logout', [LoginController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::prefix('admin')->name('admin.')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('/login', fn () => redirect()->route('login'))->name('login');
        Route::post('/login', [LoginController::class, 'store']);
        Route::get('/password', fn () => redirect()->route('password.edit'))->name('password.edit');
        Route::post('/password', [PasswordController::class, 'store'])->name('password.update');
    });

    Route::post('/logout', [LoginController::class, 'destroy'])
        ->middleware('auth')
        ->name('logout');

    Route::middleware(['auth', 'admin.area'])->group(function () {
        Route::middleware('permission:dashboard.view')->group(function () {
            Route::get('/', fn () => redirect()->route('admin.dashboard'));
            Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
        });

        Route::middleware('permission:tables.layout')->group(function () {
            Route::get('/floor-layout', [FloorLayoutController::class, 'index'])->name('floor-layout.index');
            Route::post('/floor-layout/floors', [FloorLayoutController::class, 'store'])->name('floor-layout.floors.store');
            Route::put('/floor-layout/floors/{floor}', [FloorLayoutController::class, 'update'])->name('floor-layout.floors.update');
            Route::put('/floor-layout/floors/{floor}/layout', [FloorLayoutController::class, 'save'])->name('floor-layout.save');
            Route::post('/floor-layout/floors/{floor}/assignments', [FloorLayoutController::class, 'assign'])->name('floor-layout.assign');
        });

        Route::get('/orders/{order}/kot', [KotController::class, 'show'])
            ->middleware('permission:kot.view')
            ->name('orders.kot');
        Route::get('/orders/{order}/kot/edit', [KotController::class, 'edit'])
            ->middleware('permission:kot.update')
            ->name('orders.kot.edit');
        Route::patch('/orders/{order}/kot', [KotController::class, 'update'])
            ->middleware('permission:kot.update')
            ->name('orders.kot.update');
        Route::get('/orders/{order}/bill', [OrderBillController::class, 'show'])
            ->middleware('permission:bills.view')
            ->name('orders.bill');
        Route::get('/orders/{order}/bill/print', [OrderBillController::class, 'print'])
            ->middleware('permission:bills.view')
            ->name('orders.bill.print');
        Route::post('/orders/{order}/bill/adjustments', [OrderBillController::class, 'updateAdjustments'])
            ->middleware('permission:bills.discount')
            ->name('orders.bill.adjustments');

        Route::post('/bills/{bill}/payments', [BillPaymentController::class, 'store'])
            ->middleware('permission:bills.settle')
            ->name('bills.payments.store');

        Route::prefix('menu/categories')->name('menu.categories.')->group(function () {
            Route::get('/', [MenuCategoryController::class, 'index'])
                ->middleware('permission:menu.view')
                ->name('index');
            Route::get('/create', [MenuCategoryController::class, 'create'])
                ->middleware('permission:menu.create')
                ->name('create');
            Route::post('/', [MenuCategoryController::class, 'store'])
                ->middleware('permission:menu.create')
                ->name('store');
            Route::get('/{category}/edit', [MenuCategoryController::class, 'edit'])
                ->middleware('permission:menu.update')
                ->name('edit');
            Route::put('/{category}', [MenuCategoryController::class, 'update'])
                ->middleware('permission:menu.update')
                ->name('update');
            Route::patch('/{category}/status', [MenuCategoryController::class, 'updateStatus'])
                ->middleware('permission:menu.update')
                ->name('status');
            Route::delete('/{category}', [MenuCategoryController::class, 'destroy'])
                ->middleware('permission:menu.delete')
                ->name('destroy');
        });

        Route::prefix('menu/items')->name('menu.items.')->group(function () {
            Route::get('/', [MenuItemController::class, 'index'])
                ->middleware('permission:menu.view')
                ->name('index');
            Route::get('/create', [MenuItemController::class, 'create'])
                ->middleware('permission:menu.create')
                ->name('create');
            Route::post('/', [MenuItemController::class, 'store'])
                ->middleware('permission:menu.create')
                ->name('store');
            Route::get('/{item}/edit', [MenuItemController::class, 'edit'])
                ->middleware('permission:menu.update')
                ->name('edit');
            Route::put('/{item}', [MenuItemController::class, 'update'])
                ->middleware('permission:menu.update')
                ->name('update');
            Route::patch('/{item}/status', [MenuItemController::class, 'updateStatus'])
                ->middleware('permission:menu.update')
                ->name('status');
            Route::delete('/{item}', [MenuItemController::class, 'destroy'])
                ->middleware('permission:menu.delete')
                ->name('destroy');
        });

        Route::prefix('users')->name('users.')->group(function () {
            Route::get('/', [UserController::class, 'index'])
                ->middleware('permission:users.view')
                ->name('index');
            Route::get('/create', [UserController::class, 'create'])
                ->middleware('permission:users.create')
                ->name('create');
            Route::post('/', [UserController::class, 'store'])
                ->middleware('permission:users.create')
                ->name('store');
            Route::get('/{user}/edit', [UserController::class, 'edit'])
                ->middleware('permission:users.update')
                ->name('edit');
            Route::put('/{user}', [UserController::class, 'update'])
                ->middleware('permission:users.update')
                ->name('update');
            Route::patch('/{user}/status', [UserController::class, 'updateStatus'])
                ->middleware('permission:users.update')
                ->name('status');
            Route::delete('/{user}', [UserController::class, 'destroy'])
                ->middleware('permission:users.delete')
                ->name('destroy');
        });

        Route::prefix('tables')->name('tables.')->group(function () {
            Route::get('/', [TableController::class, 'index'])
                ->middleware('permission:tables.view')
                ->name('index');
            Route::get('/create', [TableController::class, 'create'])
                ->middleware('permission:tables.create')
                ->name('create');
            Route::post('/', [TableController::class, 'store'])
                ->middleware('permission:tables.create')
                ->name('store');
            Route::get('/{table}/edit', [TableController::class, 'edit'])
                ->middleware('permission:tables.update')
                ->name('edit');
            Route::put('/{table}', [TableController::class, 'update'])
                ->middleware('permission:tables.update')
                ->name('update');
            Route::patch('/{table}/status', [TableController::class, 'updateStatus'])
                ->middleware('permission:tables.update')
                ->name('status');
            Route::delete('/{table}', [TableController::class, 'destroy'])
                ->middleware('permission:tables.delete')
                ->name('destroy');
        });

        Route::prefix('discounts')->name('discounts.')->group(function () {
            Route::get('/', [DiscountController::class, 'index'])
                ->middleware('permission:discounts.view')
                ->name('index');
            Route::get('/create', [DiscountController::class, 'create'])
                ->middleware('permission:discounts.create')
                ->name('create');
            Route::post('/', [DiscountController::class, 'store'])
                ->middleware('permission:discounts.create')
                ->name('store');
            Route::get('/{discount}/edit', [DiscountController::class, 'edit'])
                ->middleware('permission:discounts.update')
                ->name('edit');
            Route::put('/{discount}', [DiscountController::class, 'update'])
                ->middleware('permission:discounts.update')
                ->name('update');
            Route::patch('/{discount}/status', [DiscountController::class, 'updateStatus'])
                ->middleware('permission:discounts.update')
                ->name('status');
            Route::delete('/{discount}', [DiscountController::class, 'destroy'])
                ->middleware('permission:discounts.delete')
                ->name('destroy');
        });

        Route::prefix('inventory/categories')->name('inventory.categories.')->group(function () {
            Route::get('/', [InventoryCategoryController::class, 'index'])->middleware('permission:inventory.view')->name('index');
            Route::get('/create', [InventoryCategoryController::class, 'create'])->middleware('permission:inventory.create')->name('create');
            Route::post('/', [InventoryCategoryController::class, 'store'])->middleware('permission:inventory.create')->name('store');
            Route::get('/{category}', [InventoryCategoryController::class, 'show'])->middleware('permission:inventory.view')->name('show');
            Route::get('/{category}/edit', [InventoryCategoryController::class, 'edit'])->middleware('permission:inventory.update')->name('edit');
            Route::put('/{category}', [InventoryCategoryController::class, 'update'])->middleware('permission:inventory.update')->name('update');
            Route::patch('/{category}/status', [InventoryCategoryController::class, 'updateStatus'])->middleware('permission:inventory.update')->name('status');
            Route::delete('/{category}', [InventoryCategoryController::class, 'destroy'])->middleware('permission:inventory.delete')->name('destroy');
        });

        Route::prefix('inventory/units')->name('inventory.units.')->group(function () {
            Route::get('/', [UnitOfMeasureController::class, 'index'])->middleware('permission:inventory.view')->name('index');
            Route::get('/create', [UnitOfMeasureController::class, 'create'])->middleware('permission:inventory.create')->name('create');
            Route::post('/', [UnitOfMeasureController::class, 'store'])->middleware('permission:inventory.create')->name('store');
            Route::get('/{unit}', [UnitOfMeasureController::class, 'show'])->middleware('permission:inventory.view')->name('show');
            Route::get('/{unit}/edit', [UnitOfMeasureController::class, 'edit'])->middleware('permission:inventory.update')->name('edit');
            Route::put('/{unit}', [UnitOfMeasureController::class, 'update'])->middleware('permission:inventory.update')->name('update');
            Route::patch('/{unit}/status', [UnitOfMeasureController::class, 'updateStatus'])->middleware('permission:inventory.update')->name('status');
            Route::delete('/{unit}', [UnitOfMeasureController::class, 'destroy'])->middleware('permission:inventory.delete')->name('destroy');
        });

        Route::prefix('inventory/items')->name('inventory.items.')->group(function () {
            Route::get('/', [InventoryItemController::class, 'index'])->middleware('permission:inventory.view')->name('index');
            Route::get('/create', [InventoryItemController::class, 'create'])->middleware('permission:inventory.create')->name('create');
            Route::post('/', [InventoryItemController::class, 'store'])->middleware('permission:inventory.create')->name('store');
            Route::get('/{item}', [InventoryItemController::class, 'show'])->middleware('permission:inventory.view')->name('show');
            Route::get('/{item}/edit', [InventoryItemController::class, 'edit'])->middleware('permission:inventory.update')->name('edit');
            Route::put('/{item}', [InventoryItemController::class, 'update'])->middleware('permission:inventory.update')->name('update');
            Route::patch('/{item}/status', [InventoryItemController::class, 'updateStatus'])->middleware('permission:inventory.update')->name('status');
            Route::delete('/{item}', [InventoryItemController::class, 'destroy'])->middleware('permission:inventory.delete')->name('destroy');
        });

        Route::prefix('inventory/vendors')->name('inventory.vendors.')->group(function () {
            Route::get('/', [VendorController::class, 'index'])->middleware('permission:inventory.view')->name('index');
            Route::get('/create', [VendorController::class, 'create'])->middleware('permission:inventory.create')->name('create');
            Route::post('/', [VendorController::class, 'store'])->middleware('permission:inventory.create')->name('store');
            Route::get('/{vendor}', [VendorController::class, 'show'])->middleware('permission:inventory.view')->name('show');
            Route::get('/{vendor}/edit', [VendorController::class, 'edit'])->middleware('permission:inventory.update')->name('edit');
            Route::put('/{vendor}', [VendorController::class, 'update'])->middleware('permission:inventory.update')->name('update');
            Route::patch('/{vendor}/status', [VendorController::class, 'updateStatus'])->middleware('permission:inventory.update')->name('status');
            Route::delete('/{vendor}', [VendorController::class, 'destroy'])->middleware('permission:inventory.delete')->name('destroy');
        });

        Route::prefix('inventory/receipts')->name('inventory.receipts.')->group(function () {
            Route::get('/', [GoodsReceiptController::class, 'index'])->middleware('permission:inventory.receipt.view')->name('index');
            Route::get('/create', [GoodsReceiptController::class, 'create'])->middleware('permission:inventory.receipt.create')->name('create');
            Route::post('/', [GoodsReceiptController::class, 'store'])->middleware('permission:inventory.receipt.create')->name('store');
            Route::get('/items', [GoodsReceiptController::class, 'items'])->middleware('permission:inventory.receipt.view')->name('items');
            Route::get('/vendors', [GoodsReceiptController::class, 'vendors'])->middleware('permission:inventory.receipt.view')->name('vendors');
            Route::get('/{receipt}/print', [GoodsReceiptController::class, 'print'])->middleware('permission:inventory.receipt.view')->name('print');
            Route::get('/{receipt}/edit', [GoodsReceiptController::class, 'edit'])->middleware('permission:inventory.receipt.update')->name('edit');
            Route::put('/{receipt}', [GoodsReceiptController::class, 'update'])->middleware('permission:inventory.receipt.update')->name('update');
            Route::delete('/{receipt}', [GoodsReceiptController::class, 'destroy'])->middleware('permission:inventory.receipt.update')->name('destroy');
            Route::post('/{receipt}/post', [GoodsReceiptController::class, 'post'])->middleware('permission:inventory.receipt.post')->name('post');
            Route::post('/{receipt}/void', [GoodsReceiptController::class, 'void'])->middleware('permission:inventory.receipt.void')->name('void');
            Route::get('/{receipt}', [GoodsReceiptController::class, 'show'])->middleware('permission:inventory.receipt.view')->name('show');
        });

        Route::prefix('inventory/stock')->name('inventory.stock.')->middleware('permission:inventory.stock.view')->group(function () {
            Route::get('/', [InventoryStockController::class, 'index'])->name('index');
            Route::get('/movements', [InventoryStockController::class, 'movements'])->name('movements');
            Route::get('/low', [InventoryStockController::class, 'low'])->name('low');
        });

        Route::prefix('receipts')->name('receipts.')->group(function () {
            Route::get('/', [ReceiptController::class, 'index'])
                ->middleware('permission:receipts.view')
                ->name('index');
            Route::get('/create', [ReceiptController::class, 'create'])
                ->middleware('permission:receipts.create')
                ->name('create');
            Route::post('/', [ReceiptController::class, 'store'])
                ->middleware('permission:receipts.create')
                ->name('store');
            Route::get('/{receipt}/edit', [ReceiptController::class, 'edit'])
                ->middleware('permission:receipts.update')
                ->name('edit');
            Route::put('/{receipt}', [ReceiptController::class, 'update'])
                ->middleware('permission:receipts.update')
                ->name('update');
            Route::get('/{receipt}/print', [ReceiptController::class, 'print'])
                ->middleware('permission:receipts.print')
                ->name('print');
        });

        Route::prefix('reports')->name('reports.')->middleware('permission:reports.view')->group(function () {
            Route::get('/item-summary', [ReportController::class, 'itemSummary'])->name('item-summary');
            Route::get('/bill-summary', [ReportController::class, 'billSummary'])->name('bill-summary');
            Route::get('/bill-status', [ReportController::class, 'billStatus'])->name('bill-status');
            Route::get('/transaction', [ReportController::class, 'transaction'])->name('transaction');
            Route::get('/payment-mode', [ReportController::class, 'paymentMode'])->name('payment-mode');
            Route::get('/waiter', [ReportController::class, 'waiter'])->name('waiter');
            Route::get('/order-wise', [ReportController::class, 'orderWise'])->name('order-wise');
            Route::get('/item-wise', [ReportController::class, 'itemWise'])->name('item-wise');
            Route::get('/customer', [ReportController::class, 'customer'])->name('customer');
        });
    });
});

Route::prefix('counter')->name('counter.')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('/login', fn () => redirect()->route('login'))->name('login');
        Route::post('/login', [LoginController::class, 'store']);
        Route::get('/password', fn () => redirect()->route('password.edit'))->name('password.edit');
        Route::post('/password', [CounterPasswordController::class, 'store'])->name('password.update');
    });

    Route::post('/logout', [LoginController::class, 'destroy'])
        ->middleware('auth')
        ->name('logout');

    Route::middleware(['auth', 'counter.area'])->group(function () {
        Route::middleware('permission:dashboard.view')->group(function () {
            Route::get('/', fn () => redirect()->route('counter.dashboard'));
            Route::get('/dashboard', [CounterDashboardController::class, 'index'])->name('dashboard');
        });

        Route::middleware('permission:orders.view')->group(function () {
            Route::get('/order', [TakeawayOrderController::class, 'create'])->name('takeaway.create');
            Route::post('/order', [TakeawayOrderController::class, 'store'])->name('takeaway.store');
            Route::get('/menus', [TakeawayOrderController::class, 'menu'])->name('takeaway.menu');
            Route::post('/menus/{menuItem}', [TakeawayOrderController::class, 'add'])->name('takeaway.menu.add');
            Route::post('/menus/{menuItem}/increase', [TakeawayOrderController::class, 'increase'])->name('takeaway.menu.increase');
            Route::post('/menus/{menuItem}/decrease', [TakeawayOrderController::class, 'decrease'])->name('takeaway.menu.decrease');
            Route::post('/menus/{menuItem}/packing', [TakeawayOrderController::class, 'menuPacking'])->name('takeaway.menu.packing');
            Route::get('/cart', [TakeawayOrderController::class, 'cart'])->name('takeaway.cart');
            Route::post('/cart/{menuItem}/increase', [TakeawayOrderController::class, 'cartIncrease'])->name('takeaway.cart.increase');
            Route::post('/cart/{menuItem}/decrease', [TakeawayOrderController::class, 'cartDecrease'])->name('takeaway.cart.decrease');
            Route::post('/cart/{menuItem}/packing-increase', [TakeawayOrderController::class, 'packingIncrease'])->name('takeaway.cart.packing-increase');
            Route::post('/cart/{menuItem}/packing-decrease', [TakeawayOrderController::class, 'packingDecrease'])->name('takeaway.cart.packing-decrease');
            Route::post('/cart/{menuItem}/remove', [TakeawayOrderController::class, 'remove'])->name('takeaway.cart.remove');
            Route::post('/cart/place', [TakeawayOrderController::class, 'place'])->name('takeaway.place');
        });

        Route::get('/orders/{order}/kot', [CounterKotController::class, 'show'])
            ->middleware('permission:kot.view')
            ->name('orders.kot');
        Route::get('/orders/{order}/kot/edit', [CounterKotController::class, 'edit'])
            ->middleware('permission:kot.update')
            ->name('orders.kot.edit');
        Route::patch('/orders/{order}/kot', [CounterKotController::class, 'update'])
            ->middleware('permission:kot.update')
            ->name('orders.kot.update');
        Route::get('/orders/{order}/bill', [CounterOrderBillController::class, 'show'])
            ->middleware('permission:bills.view')
            ->name('orders.bill');
        Route::get('/orders/{order}/bill/print', [CounterOrderBillController::class, 'print'])
            ->middleware('permission:bills.view')
            ->name('orders.bill.print');
        Route::post('/orders/{order}/bill/adjustments', [CounterOrderBillController::class, 'updateAdjustments'])
            ->middleware('permission:bills.discount')
            ->name('orders.bill.adjustments');

        Route::post('/bills/{bill}/payments', [CounterBillPaymentController::class, 'store'])
            ->middleware('permission:bills.settle')
            ->name('bills.payments.store');

        Route::post('/bills/{bill}/split-payments', [CounterBillPaymentController::class, 'storeSplit'])
            ->middleware('permission:bills.settle')
            ->name('bills.split-payments.store');

        Route::prefix('reports')->name('reports.')->middleware('permission:reports.view')->group(function () {
            Route::get('/transaction', [CounterReportController::class, 'transaction'])->name('transaction');
            Route::get('/order-wise', [CounterReportController::class, 'orderWise'])->name('order-wise');
            Route::get('/item-wise', [CounterReportController::class, 'itemWise'])->name('item-wise');
            Route::get('/waiter', [CounterReportController::class, 'waiter'])->name('waiter');
            Route::get('/payment-mode', [CounterReportController::class, 'paymentMode'])->name('payment-mode');
            Route::get('/bill-status', [CounterReportController::class, 'billStatus'])->name('bill-status');
            Route::get('/customer', [CounterReportController::class, 'customer'])->name('customer');
        });
    });
});
