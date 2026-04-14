<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', '/settings/appearance');

    Route::get('settings/appearance', [App\Http\Controllers\SettingsController::class, 'createAppearance'])->name('appearance.edit');
});
