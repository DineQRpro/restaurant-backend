<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// API v1 route group (foundation only; no business endpoints yet)
Route::prefix('v1')->group(function () {
    require __DIR__ . '/api/v1.php';
});
