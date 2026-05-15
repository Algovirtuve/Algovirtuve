<?php

use App\Http\Controllers\Shopping_management\shopping_controller;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::get('shopping-plan', [shopping_controller::class, 'showProductsPlanPage'])->name('shopping_plan.render');

    Route::get('shopping-plan/recipes', [shopping_controller::class, 'getRecipes'])->name('shopping_plan.getRecipes');

    Route::post('shopping-plan/generate', [shopping_controller::class, 'generateProductsPlan'])
        ->name('shopping_plan.generateProductsPlan');

    Route::post('shopping-plan/searchProducts', [shopping_controller::class, 'searchForProducts'])
        ->name('shopping_plan.searchForProducts');

    Route::patch('shopping-plan/{shoppingPlan}/products', [shopping_controller::class, 'insertNewProduct'])
        ->name('shopping_plan.insertNewProduct');

    Route::patch('shopping-plan/{shoppingPlan}/store-products/{productId}', [shopping_controller::class, 'removeProduct'])
        ->name('shopping_plan.removeProduct');
});
