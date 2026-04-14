<?php

use App\Http\Controllers\SettingsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', '/settings/appearance');

    Route::get('settings/appearance', [SettingsController::class, 'createAppearance'])->name('appearance.edit');
});
