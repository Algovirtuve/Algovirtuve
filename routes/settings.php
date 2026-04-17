<?php

use App\Http\Controllers\Personalization\settings_controller;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', '/settings/appearance');

    Route::get('settings/appearance', [settings_controller::class, 'createAppearance'])->name('appearance.edit');
});
