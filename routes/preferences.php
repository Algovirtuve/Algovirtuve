<?php

use App\Http\Controllers\Personalization\personalization_controller;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::get('preferences', [personalization_controller::class, 'preferences'])->name('preferences.index');
    Route::get('preferences/create', [personalization_controller::class, 'createPreference'])->name('preferences.create');
    Route::post('preferences', [personalization_controller::class, 'storePreference'])->name('preferences.store');
    Route::delete('preferences/{preference}', [personalization_controller::class, 'destroyPreference'])->name('preferences.destroy');

    Route::get('suggestions', [personalization_controller::class, 'suggestions'])->name('suggestions.index');
    Route::redirect('suggestions/{preference}/like', '/suggestions');
    Route::redirect('suggestions/{preference}/dislike', '/suggestions');
    Route::patch('suggestions/{preference}/like', [personalization_controller::class, 'likeSuggestion'])->name('suggestions.like');
    Route::patch('suggestions/{preference}/dislike', [personalization_controller::class, 'dislikeSuggestion'])->name('suggestions.dislike');
});
