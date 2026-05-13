<?php

use App\Http\Controllers\Shopping_managment\shops_api_controller;
use Illuminate\Support\Facades\Route;

// Fake Shops API inside the app (used by products_service).
Route::prefix('shops-api')->group(function () {
    Route::get('products', [shops_api_controller::class, 'getProducts'])->name('shops_api.getProducts');
    Route::get('shops', [shops_api_controller::class, 'getShops'])->name('shops_api.getShops');
});
