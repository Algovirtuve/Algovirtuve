<?php

use App\Http\Controllers\Recipe_generation\tool_controller;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::get('tools', [tool_controller::class, 'index'])->name('tools.index');
    Route::get('tools/create', [tool_controller::class, 'create'])->name('tools.create');
    Route::post('tools', [tool_controller::class, 'store'])->name('tools.store');
    Route::delete('tools/{tool}', [tool_controller::class, 'destroy'])->name('tools.destroy');
});
