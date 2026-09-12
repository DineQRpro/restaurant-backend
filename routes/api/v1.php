<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CheckoutController;
use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\DineTypeController;
use App\Http\Controllers\Api\V1\Menu\MenuCategoryController;
use App\Http\Controllers\Api\V1\Menu\MenuItemController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\OrderItemController;
use App\Http\Controllers\Api\V1\TableController;
use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
    });
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/users', [UserController::class, 'index'])
        ->middleware('permission:users.view');
    Route::post('/users', [UserController::class, 'store'])
        ->middleware('permission:users.create');
    Route::get('/users/{user}', [UserController::class, 'show'])
        ->middleware('permission:users.view');
    Route::put('/users/{user}', [UserController::class, 'update'])
        ->middleware('permission:users.update');
    Route::patch('/users/{user}/status', [UserController::class, 'updateStatus'])
        ->middleware('permission:users.update');
    Route::delete('/users/{user}', [UserController::class, 'destroy'])
        ->middleware('permission:users.delete');

    Route::prefix('menu')->group(function () {
        Route::get('/categories', [MenuCategoryController::class, 'index'])
            ->middleware('permission:menu.view');
        Route::post('/categories', [MenuCategoryController::class, 'store'])
            ->middleware('permission:menu.create');
        Route::get('/categories/{category}', [MenuCategoryController::class, 'show'])
            ->middleware('permission:menu.view');
        Route::put('/categories/{category}', [MenuCategoryController::class, 'update'])
            ->middleware('permission:menu.update');
        Route::patch('/categories/{category}/status', [MenuCategoryController::class, 'updateStatus'])
            ->middleware('permission:menu.update');
        Route::delete('/categories/{category}', [MenuCategoryController::class, 'destroy'])
            ->middleware('permission:menu.delete');

        Route::get('/items', [MenuItemController::class, 'index'])
            ->middleware('permission:menu.view');
        Route::post('/items', [MenuItemController::class, 'store'])
            ->middleware('permission:menu.create');
        Route::get('/items/{item}', [MenuItemController::class, 'show'])
            ->middleware('permission:menu.view');
        Route::put('/items/{item}', [MenuItemController::class, 'update'])
            ->middleware('permission:menu.update');
        Route::patch('/items/{item}/status', [MenuItemController::class, 'updateStatus'])
            ->middleware('permission:menu.update');
        Route::delete('/items/{item}', [MenuItemController::class, 'destroy'])
            ->middleware('permission:menu.delete');
    });

    Route::get('/tables', [TableController::class, 'index'])
        ->middleware('permission:tables.view');
    Route::get('/tables/{table}', [TableController::class, 'show'])
        ->middleware('permission:tables.view');

    Route::get('/dine-types', [DineTypeController::class, 'index'])
        ->middleware('permission:orders.create');

    Route::get('/customers/lookup', [CustomerController::class, 'lookup'])
        ->middleware('permission:orders.create');
    Route::post('/customers', [CustomerController::class, 'store'])
        ->middleware('permission:orders.create');

    Route::post('/orders', [OrderController::class, 'store'])
        ->middleware('permission:orders.create');

    Route::get('/tables/{table}/active-order/items', [OrderItemController::class, 'activeForTable'])
        ->middleware('permission:orders.view');

    Route::patch('/order-items/status', [OrderItemController::class, 'updateStatus'])
        ->middleware('permission:orders.item_status');

    Route::get('/tables/{table}/checkout-preview', [CheckoutController::class, 'preview'])
        ->middleware('permission:bills.create');

    Route::post('/orders/{order}/checkout', [CheckoutController::class, 'store'])
        ->middleware('permission:bills.create');
});
