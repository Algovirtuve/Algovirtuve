<?php

use App\Http\Controllers\Health_managment\diet_controller;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::get('diet', [diet_controller::class, 'index'])->name('diet.index');

    Route::get('diet/generate', [diet_controller::class, 'viewGenerateDietPlan'])->name('diet.generate.view');
    Route::post('diet/generate', [diet_controller::class, 'generateDietPlan'])->name('diet.generate');
    Route::get('diet/plan', [diet_controller::class, 'dietPlanPage'])->name('diet.plan');
});
