<?php

use App\Http\Controllers\Shopping_managment\shopping_controller;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::get('shopping-plan', [shopping_controller::class, 'render'])->name('shopping_plan.render');

    Route::get('shopping-plan/recipes', [shopping_controller::class, 'getRecipes'])->name('shopping_plan.getRecipes');

    Route::post('shopping-plan/generate', [shopping_controller::class, 'generateProductsPlan'])
        ->name('shopping_plan.generateProductsPlan');

    Route::post('shopping-plan/{shoppingPlan}/products', [shopping_controller::class, 'insertNewProduct'])
        ->name('shopping_plan.insertNewProduct');

    Route::delete('shopping-plan/{shoppingPlan}/store-products/{storeProduct}', [shopping_controller::class, 'removeProduct'])
        ->name('shopping_plan.removeProduct');
});
