<?php

use App\Http\Controllers\Recipe_generation\ingredients_controller;
use App\Http\Controllers\Recipe_generation\tool_controller;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::get('tools', [tool_controller::class, 'index'])->name('tools.index');
    Route::get('tools/create', [tool_controller::class, 'create'])->name('tools.create');
    Route::post('tools', [tool_controller::class, 'store'])->name('tools.store');
    Route::delete('tools/{tool}', [tool_controller::class, 'destroy'])->name('tools.destroy');

    Route::get('ingredients', [ingredients_controller::class, 'index'])->name('ingredients.index');
    Route::get('ingredients/create', [ingredients_controller::class, 'create'])->name('ingredients.create');
    Route::post('ingredients', [ingredients_controller::class, 'store'])->name('ingredients.store');
    Route::delete('ingredients/{ingredient}', [ingredients_controller::class, 'destroy'])->name('ingredients.destroy');
});
