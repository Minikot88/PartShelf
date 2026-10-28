<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FileTransferController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductExcelController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\StockCountController;
use App\Http\Controllers\StorageLocationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::post('/login', LoginController::class)->middleware('throttle:login');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', fn (Request $request) => response()->json([
        'user' => $request->user()->only(['id', 'name', 'email', 'role']),
    ]));

    Route::post('/logout', LogoutController::class);
    Route::get('/file-transport', fn () => response()->json(['mode' => config('partshelf.file_transport')]));
    Route::post('/file-transfers', [FileTransferController::class, 'store'])->middleware(['can:manage-catalog', 'throttle:30,1']);
    Route::get('/products/{product}/image', [FileTransferController::class, 'image']);
    Route::get('/dashboard', DashboardController::class);

    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/products/resolve', [ProductController::class, 'resolveCode']);
    Route::middleware('can:manage-catalog')->group(function () {
        Route::get('/products/import/template', [ProductExcelController::class, 'template']);
        Route::post('/products/import/preview', [ProductExcelController::class, 'preview']);
        Route::post('/products/import/confirm', [ProductExcelController::class, 'confirm']);
        Route::get('/products/export', [ProductExcelController::class, 'exportProducts']);
        Route::get('/stock-counts/export', [ProductExcelController::class, 'exportCountResults']);
    });
    Route::get('/products/{product}', [ProductController::class, 'show']);
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::get('/locations', [StorageLocationController::class, 'index']);
    Route::get('/locations/{location}/products', [StorageLocationController::class, 'products']);
    Route::get('/stock/balances', [StockController::class, 'balances']);
    Route::get('/products/{product}/movements', [StockController::class, 'movements']);
    Route::get('/stock-counts', [StockCountController::class, 'index']);
    Route::post('/stock-counts', [StockCountController::class, 'start']);
    Route::get('/stock-counts/{count}', [StockCountController::class, 'show'])->whereNumber('count');
    Route::post('/stock-counts/{count}/lookup', [StockCountController::class, 'lookup'])->whereNumber('count');
    Route::post('/stock-counts/{count}/items/{item}/counts', [StockCountController::class, 'record'])->whereNumber(['count', 'item']);
    Route::post('/stock-counts/{count}/items/{item}/recounts', [StockCountController::class, 'record'])->defaults('recount', true)->whereNumber(['count', 'item']);
    Route::post('/stock-counts/{count}/items/{item}/correction', [StockCountController::class, 'correct'])->middleware('can:correct-stock')->whereNumber(['count', 'item']);

    Route::middleware('can:manage-stock')->group(function () {
        Route::post('/stock/opening-balances', [StockController::class, 'opening']);
        Route::post('/stock/receipts', [StockController::class, 'receipt']);
    });

    Route::middleware('can:manage-catalog')->group(function () {
        Route::post('/products', [ProductController::class, 'store']);
        Route::put('/products/{product}', [ProductController::class, 'update']);
        Route::post('/categories', [CategoryController::class, 'store']);
        Route::put('/categories/{category}', [CategoryController::class, 'update']);
        Route::put('/products/{product}/location', [ProductController::class, 'assignLocation']);
        Route::post('/locations', [StorageLocationController::class, 'store']);
        Route::put('/locations/{location}', [StorageLocationController::class, 'update']);
    });

    // P03 establishes the correction authorization guard; P09 implements the operation.
    Route::post('/stock/corrections', fn () => response()->json([
        'message' => 'Stock corrections are not implemented yet.',
    ], 501))->middleware('can:correct-stock');
});

Route::get('/health', function () {
    try {
        DB::select('SELECT 1');

        return response()->json([
            'status' => 'ok',
            'database' => 'ok',
        ]);
    } catch (Throwable) {
        return response()->json([
            'status' => 'degraded',
            'database' => 'unavailable',
        ], 503);
    }
});
