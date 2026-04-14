<?php

use App\Http\Controllers\PreferenceController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::get('preferences', [PreferenceController::class, 'preferences'])->name('preferences.index');
    Route::get('preferences/create', [PreferenceController::class, 'createPreference'])->name('preferences.create');
    Route::post('preferences', [PreferenceController::class, 'storePreference'])->name('preferences.store');
    Route::delete('preferences/{preference}', [PreferenceController::class, 'destroyPreference'])->name('preferences.destroy');
});
