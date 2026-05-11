<?php

use App\Http\Controllers\Health_managment\diet_controller;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::get('diet', [diet_controller::class, 'index'])->name('diet.index');

    Route::get('diet/generate', [diet_controller::class, 'viewGenerateDietPlan'])->name('diet.generate.view');
    Route::post('diet/temp/macros', [diet_controller::class, 'insertToTempMacros'])->name('diet.temp.macros');
    Route::post('diet/temp/type', [diet_controller::class, 'insertToTempType'])->name('diet.temp.type');
    Route::post('diet/temp/calorie', [diet_controller::class, 'insertToTempCalorie'])->name('diet.temp.calorie');
    Route::post('diet/generate', [diet_controller::class, 'generateDietPlan'])->name('diet.generate');
});
