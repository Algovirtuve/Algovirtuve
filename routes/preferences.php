<?php

use App\Http\Controllers\Personalization\personalization_controller;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::get('preferences', [personalization_controller::class, 'preferences'])->name('preferences.index');
    Route::get('preferences/create', [personalization_controller::class, 'createPreference'])->name('preferences.create');
    Route::post('preferences', [personalization_controller::class, 'storePreference'])->name('preferences.store');
    Route::delete('preferences/{preference}', [personalization_controller::class, 'destroyPreference'])->name('preferences.destroy');
});
