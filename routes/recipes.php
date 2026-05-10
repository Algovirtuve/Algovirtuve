<?php

use App\Http\Controllers\Health_managment\recipe_controller;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::get('recipes', [recipe_controller::class, 'index'])->name('recipes.index');
    Route::post('recipes/request', [recipe_controller::class, 'createRequest'])->name('recipes.request');
    Route::patch('recipes/{recipe}', [recipe_controller::class, 'update'])->name('recipes.update');
    Route::delete('recipes/{recipe}', [recipe_controller::class, 'destroy'])->name('recipes.destroy');
});
