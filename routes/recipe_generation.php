<?php

use App\Http\Controllers\Recipe_generation\ingredients_controller;
use App\Http\Controllers\Recipe_generation\tool_controller;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::get('tools', [tool_controller::class, 'showTools'])->name('tools.index');
    Route::get('tools/create', [tool_controller::class, 'showToolCreationPage'])->name('tools.create');
    Route::post('tools', [tool_controller::class, 'createTool'])->name('tools.store');
    Route::delete('tools/{tool}', [tool_controller::class, 'deleteTool'])->name('tools.destroy');

    Route::get('ingredients', [ingredients_controller::class, 'showIngredients'])->name('ingredients.index');
    Route::get('ingredients/create', [ingredients_controller::class, 'showIngredientCreationPage'])->name('ingredients.create');
    Route::post('ingredients', [ingredients_controller::class, 'createIngredient'])->name('ingredients.store');
    Route::delete('ingredients/{ingredient}', [ingredients_controller::class, 'deleteIngredient'])->name('ingredients.destroy');
});
