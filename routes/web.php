<?php

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\BillPaymentController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\KotController;
use App\Http\Controllers\Admin\Menu\MenuCategoryController;
use App\Http\Controllers\Admin\Menu\MenuItemController;
use App\Http\Controllers\Admin\OrderBillController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\TableController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Counter\AuthController as CounterAuthController;
use App\Http\Controllers\Counter\BillPaymentController as CounterBillPaymentController;
use App\Http\Controllers\Counter\DashboardController as CounterDashboardController;
use App\Http\Controllers\Counter\KotController as CounterKotController;
use App\Http\Controllers\Counter\OrderBillController as CounterOrderBillController;
use App\Http\Controllers\Counter\ReportController as CounterReportController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::prefix('admin')->name('admin.')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('/login', [AuthController::class, 'create'])->name('login');
        Route::post('/login', [AuthController::class, 'store']);
    });

    Route::post('/logout', [AuthController::class, 'destroy'])
        ->middleware('auth')
        ->name('logout');

    Route::middleware(['auth', 'admin.area'])->group(function () {
        Route::middleware('permission:dashboard.view')->group(function () {
            Route::get('/', fn () => redirect()->route('admin.dashboard'));
            Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
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
        Route::get('/login', [CounterAuthController::class, 'create'])->name('login');
        Route::post('/login', [CounterAuthController::class, 'store']);
    });

    Route::post('/logout', [CounterAuthController::class, 'destroy'])
        ->middleware('auth')
        ->name('logout');

    Route::middleware(['auth', 'counter.area'])->group(function () {
        Route::middleware('permission:dashboard.view')->group(function () {
            Route::get('/', fn () => redirect()->route('counter.dashboard'));
            Route::get('/dashboard', [CounterDashboardController::class, 'index'])->name('dashboard');
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
